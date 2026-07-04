<?php

/*
|--------------------------------------------------------------------------
| CheckResourcePermission — dynamic route-name → permission gating
|--------------------------------------------------------------------------
|
| src/Http/Middleware/CheckResourcePermission.php güvenlik-kritik davranışını
| uçtan uca doğrular. Middleware, route adının son iki segmentinden izin
| türetir (admin.users.index → users.read) ve şu kuralları uygular:
|
|   1. Türetme  — action → ability map (index/show→read, store→create, …).
|   2. Yetki     — çözülen izin DB'de seed'liyse, kullanıcı o izne sahip
|                  olmalı; değilse AuthorizationException (403).
|   3. Seed'siz  — çözülen izin DB'de YOKSA: production → deny, non-production
|                  → warn + allow (unutulan izin satırını sessizce açmamak için).
|   4. Sub-resource — ?type=student ile users.read → users:student.read'e
|                  yükseltilir, ancak yalnız o sub-izin seed'liyse.
|   5. Pass-through — route adı yok / segment < 2 / bilinmeyen action /
|                  explicit izin verilmemiş → izin çözülemez → geçilir.
|
| Test middleware'i doğrudan handle() ile çağırır: route resolver + user
| resolver kontrollü kurulur. Middleware KODU değiştirilmez — yalnız test.
| Kurulum Spatie permission stack'ine dayanır (PermissionMiddlewareTestCase).
|
*/

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

// ──────────────────────────────────────────────────────────────────────────────
// Yerel test kullanıcısı — App\Models\User gerektirmez; Spatie HasRoles +
// Foundation Authorizable (can()) taşır, böylece $user->can('x') gerçek Gate
// üzerinden Spatie checkPermissionTo'ya düşer.
// ──────────────────────────────────────────────────────────────────────────────

class PermMiddlewareTestUser extends AuthUser
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    /** Spatie izinleri default 'web' guard'ında üretilir; kullanıcı da onunla eşleşmeli. */
    protected string $guard_name = 'web';
}

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/** DB'de bir izni (default guard) oluşturur. */
function seedPermission(string $name): Permission
{
    return Permission::findOrCreate($name);
}

/**
 * İsteğe bağlı izinlerle bir kullanıcı yaratır.
 *
 * @param  string[]  $permissions  önceden seed'lenmiş izin adları
 */
function makePermUser(array $permissions = []): PermMiddlewareTestUser
{
    $user = new PermMiddlewareTestUser;
    $user->forceFill([
        'name' => 'Test',
        'email' => uniqid('u', true).'@x.test',
        'password' => 'x',
    ])->save();

    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

/**
 * Adı belli (ya da adsız) bir route'a bağlı Request kurar.
 *
 * @param  array<string, mixed>  $query
 */
function permRequest(?string $routeName, array $query = [], ?PermMiddlewareTestUser $user = null): Request
{
    $request = Request::create('/admin/x', 'GET', $query);

    $action = $routeName !== null ? ['as' => $routeName] : [];
    $route = new Route(['GET'], '/admin/x', $action);

    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $user);

    return $request;
}

/** Middleware'i çalıştırır; next() basit "ok" 200 döner. */
function runMiddleware(Request $request, ?string $explicitPermission = null): Response
{
    $middleware = new CheckResourcePermission;

    return $middleware->handle(
        $request,
        fn (Request $req): Response => new Response('ok', 200),
        $explicitPermission,
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Route-name → permission türetme + yetkili/yetkisiz
// ──────────────────────────────────────────────────────────────────────────────

it('allows a mapped route when the user has the resolved permission', function (): void {
    seedPermission('users.read');
    $user = makePermUser(['users.read']);

    $response = runMiddleware(permRequest('admin.users.index', user: $user));

    expect($response->getContent())->toBe('ok');
});

it('denies (403) a mapped route when the user lacks the resolved permission', function (): void {
    seedPermission('users.read');
    $user = makePermUser([]); // izin yok

    expect(fn () => runMiddleware(permRequest('admin.users.index', user: $user)))
        ->toThrow(AuthorizationException::class);
});

it('maps each action segment to the correct ability', function (): void {
    // index/show → read, store → create, update → update, destroy → delete,
    // export → export. Her biri için ayrı izin seed edip yetkili kullanıcı geçmeli.
    $cases = [
        'admin.users.index' => 'users.read',
        'admin.users.show' => 'users.read',
        'admin.users.store' => 'users.create',
        'admin.users.update' => 'users.update',
        'admin.users.destroy' => 'users.delete',
        'admin.users.export' => 'users.export',
    ];

    foreach ($cases as $ability) {
        seedPermission($ability);
    }

    // Tüm ability'lere sahip kullanıcı her route'u geçer.
    $user = makePermUser(array_values($cases));

    foreach ($cases as $routeName => $ability) {
        $response = runMiddleware(permRequest($routeName, user: $user));
        expect($response->getContent())->toBe('ok', "route {$routeName} → {$ability} geçmeliydi");
    }
});

it('denies when the user has a different resolved ability than required', function (): void {
    seedPermission('users.read');
    seedPermission('users.delete');
    // Kullanıcı yalnız read'e sahip; destroy → users.delete istenir → 403.
    $user = makePermUser(['users.read']);

    expect(fn () => runMiddleware(permRequest('admin.users.destroy', user: $user)))
        ->toThrow(AuthorizationException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Seed'siz izin — production deny vs non-production warn+allow
// ──────────────────────────────────────────────────────────────────────────────

it('denies an unseeded resolved permission in production', function (): void {
    // reports.read hiç seed edilmedi.
    app()->detectEnvironment(fn () => 'production');
    $user = makePermUser([]);

    expect(fn () => runMiddleware(permRequest('admin.reports.index', user: $user)))
        ->toThrow(AuthorizationException::class);
});

it('warns and allows an unseeded resolved permission in non-production', function (): void {
    Log::spy();
    // Ortam testbench default'u ('testing') — production değil.
    expect(app()->environment('production'))->toBeFalse();

    $user = makePermUser([]);

    $response = runMiddleware(permRequest('admin.reports.index', user: $user));

    expect($response->getContent())->toBe('ok');
    Log::shouldHaveReceived('warning')->once();
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Sub-resource (?type=…) çözümü
// ──────────────────────────────────────────────────────────────────────────────

it('upgrades to a sub-resource permission when ?type is a seeded sub-permission', function (): void {
    seedPermission('users.read');
    seedPermission('users:student.read');

    // Kullanıcı YALNIZ sub-izne sahip; base users.read'e sahip değil.
    $user = makePermUser(['users:student.read']);

    $response = runMiddleware(permRequest('admin.users.index', ['type' => 'student'], $user));

    expect($response->getContent())->toBe('ok');
});

it('denies when the sub-resource permission is required but the user only has the base permission', function (): void {
    seedPermission('users.read');
    seedPermission('users:student.read');

    // Kullanıcı base'e sahip ama sub-izne değil; ?type=student sub'a yükseltir → 403.
    $user = makePermUser(['users.read']);

    expect(fn () => runMiddleware(permRequest('admin.users.index', ['type' => 'student'], $user)))
        ->toThrow(AuthorizationException::class);
});

it('falls back to the base permission when the sub-resource permission is not seeded', function (): void {
    // Yalnız base seed'li; users:teacher.read yok → base users.read kullanılır.
    seedPermission('users.read');
    $user = makePermUser(['users.read']);

    $response = runMiddleware(permRequest('admin.users.index', ['type' => 'teacher'], $user));

    expect($response->getContent())->toBe('ok');
});

it('ignores a malformed ?type value and uses the base permission', function (): void {
    // Regex dışı type ("../../etc") → sub-izin denenmez, base users.read kullanılır.
    seedPermission('users.read');
    $user = makePermUser(['users.read']);

    $response = runMiddleware(permRequest('admin.users.index', ['type' => '../../etc'], $user));

    expect($response->getContent())->toBe('ok');
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. Misafir (guest) davranışı
// ──────────────────────────────────────────────────────────────────────────────

it('denies a guest on a mapped, seeded route', function (): void {
    seedPermission('users.read');
    // user resolver null → misafir.
    expect(fn () => runMiddleware(permRequest('admin.users.index', user: null)))
        ->toThrow(AuthorizationException::class);
});

it('allows a guest through when the permission cannot be resolved', function (): void {
    // Bilinmeyen action → izin çözülemez → guest bile geçer (pass-through).
    $response = runMiddleware(permRequest('admin.users.customThing', user: null));

    expect($response->getContent())->toBe('ok');
});

// ──────────────────────────────────────────────────────────────────────────────
// 5. Pass-through kenar durumları
// ──────────────────────────────────────────────────────────────────────────────

it('passes through when the route has no name', function (): void {
    $response = runMiddleware(permRequest(null, user: makePermUser([])));

    expect($response->getContent())->toBe('ok');
});

it('passes through when the route name has fewer than two segments', function (): void {
    // "dashboard" tek segment → resolvePermission null → geç.
    $response = runMiddleware(permRequest('dashboard', user: makePermUser([])));

    expect($response->getContent())->toBe('ok');
});

it('passes through when the action segment is not in the ability map', function (): void {
    // "reorder" map'te yok → izin çözülemez → geç.
    $response = runMiddleware(permRequest('admin.users.reorder', user: makePermUser([])));

    expect($response->getContent())->toBe('ok');
});

// ──────────────────────────────────────────────────────────────────────────────
// 6. Explicit izin argümanı (route adından türetme yerine)
// ──────────────────────────────────────────────────────────────────────────────

it('uses an explicit permission argument instead of resolving from the route name', function (): void {
    seedPermission('reports.read');
    $user = makePermUser(['reports.read']);

    // Route adı users.index olsa da explicit reports.read baskındır.
    $response = runMiddleware(permRequest('admin.users.index', user: $user), 'reports.read');

    expect($response->getContent())->toBe('ok');
});

it('denies when the explicit permission is seeded but the user lacks it', function (): void {
    seedPermission('reports.read');
    $user = makePermUser([]); // reports.read yok

    expect(fn () => runMiddleware(permRequest('admin.users.index', user: $user), 'reports.read'))
        ->toThrow(AuthorizationException::class);
});

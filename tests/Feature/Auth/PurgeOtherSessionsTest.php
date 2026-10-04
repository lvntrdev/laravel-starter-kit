<?php

/*
|--------------------------------------------------------------------------
| "Log out other browser sessions" — every driver, this device survives
|--------------------------------------------------------------------------
|
| PurgeOtherSessionsAction used to delete `sessions` rows and nothing else, so
| on the file / redis / memcached / cookie drivers the button reported success
| and ended nothing. It now rehashes the password through the session guard
| (AuthenticateSession, attached to `web` by the provider, logs out every
| session stamped with the old hash), cycles the remember token, and still
| deletes rows on the `database` driver.
|
| What is locked here:
|
|   1. Action contract — a wrong password changes nothing; a right one stores
|      a NEW hash of the SAME password, leaves `password_changed_at` alone
|      (the real User `saving` hook is live in this file, and a control case
|      proves it), cycles the remember token and fires OtherDeviceLogout. A
|      guard that is not a session guard authenticated as the same user is a
|      LogicException — a purge that cannot run never reports success.
|   2. `database` driver — the user's other rows go, the current row and every
|      other owner's row stay.
|   3. Request level on the FILE driver (no row delete to hide behind): the
|      other device is redirected to login and is a guest afterwards; the
|      purging device stays signed in and keeps its remember-me cookie; a
|      remember-me cookie carrying the old token resolves nobody; a session
|      that was never stamped (opened before the upgrade) is NOT logged out.
|      A user changing their OWN password through UpdateUserAction (the Users
|      screen saves a route-bound copy, not the guard's instance) stays signed
|      in, while their other device is logged out.
|   4. Wiring — attachSessionAuthenticationMiddleware appends to `web` only,
|      never creates a missing group, never duplicates the class or the
|      `auth.session` alias.
|
| Every request goes through PosBrowser, which forgets the guards, the session
| store and the queued cookies first — one test case is one process, so
| without that a second "device" would read the first one's session from
| memory and the test would pass for the wrong reason.
|
| Helpers carry a `pos` prefix — Pest helpers are global for the whole process.
|
*/

use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Lvntr\StarterKit\Domain\Session\Actions\PurgeOtherSessionsAction;
use Lvntr\StarterKit\Domain\User\Actions\UpdateUserAction;
use Lvntr\StarterKit\Domain\User\DTOs\UserDTO;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;
use Spatie\Permission\PermissionRegistrar;

if (! class_exists(User::class)) {
    require_once dirname(__DIR__, 3).'/stubs/app/Models/User.php';
}

/**
 * The shipped consumer User model — its own `saving` hook, not a copy —
 * pointed at a table this file owns.
 */
class PurgeSessionsUser extends User
{
    protected $table = 'purge_sessions_users';
}

const POS_PASSWORD = 'Valid-Password-1!';

const POS_PASSWORD_CHANGED_AT = '2026-01-01 00:00:00';

/**
 * One browser: holds the session and remember-me cookies across requests the
 * way a real cookie jar does, and starts every request from a clean process.
 */
final class PosBrowser
{
    /** @var array<string, string> */
    public array $cookies = [];

    public function __construct(private readonly Testbench $test) {}

    public function get(string $uri): TestResponse
    {
        return $this->send('GET', $uri);
    }

    /** @param  array<string, mixed>  $data */
    public function post(string $uri, array $data = []): TestResponse
    {
        return $this->send('POST', $uri, $data);
    }

    /** @param  array<string, mixed>  $data */
    private function send(string $method, string $uri, array $data = []): TestResponse
    {
        // A request from another device is another PHP process: no guard with
        // a cached user, no session store holding the last request's data,
        // no cookie still queued from the previous response.
        app('auth')->forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        app('cookie')->flushQueuedCookies();

        $encrypted = [];

        foreach ($this->cookies as $name => $value) {
            $encrypted[$name] = encrypt(CookieValuePrefix::create($name, app('encrypter')->getKey()).$value, false);
        }

        $response = $this->test->call($method, $uri, $data, $encrypted);

        foreach ($response->headers->getCookies() as $cookie) {
            $name = $cookie->getName();

            if (! in_array($name, [config('session.cookie'), posRecallerName()], true)) {
                continue;
            }

            if ($cookie->isCleared()) {
                unset($this->cookies[$name]);

                continue;
            }

            $this->cookies[$name] = (string) $response->getCookie($name)->getValue();
        }

        return $response;
    }
}

function posUser(string $email = 'ada-purge@example.test'): PurgeSessionsUser
{
    $user = PurgeSessionsUser::create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => $email,
        'password' => POS_PASSWORD,
        'status' => 'active',
    ]);

    // An explicit assignment wins over the saving hook — this is the value a
    // purge must leave untouched.
    $user->password_changed_at = Carbon::parse(POS_PASSWORD_CHANGED_AT);
    $user->setRememberToken(Str::random(60));
    $user->save();

    return $user->refresh();
}

/** Write one `sessions` row. A null owner is a guest session. */
function posSessionRow(?string $userId): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    return $id;
}

/** @return ArrayObject<int, OtherDeviceLogout> */
function posCaptureOtherDeviceLogout(): ArrayObject
{
    $captured = new ArrayObject;

    Event::listen(OtherDeviceLogout::class, function (OtherDeviceLogout $event) use ($captured): void {
        $captured[] = $event;
    });

    return $captured;
}

function posRecallerName(): string
{
    return Auth::guard('web')->getRecallerName();
}

/** The provider's real attach method — the logic under test, not a copy. */
function posAttach(): void
{
    $provider = app()->getProvider(StarterKitServiceProvider::class);
    (new ReflectionMethod($provider, 'attachSessionAuthenticationMiddleware'))->invoke($provider, app('router'));
}

/** Replace the router's middleware groups wholesale (Router has no remover). */
function posSetGroups(array $groups): void
{
    (new ReflectionProperty(Router::class, 'middlewareGroups'))->setValue(app('router'), $groups);
}

function posSessionDir(): string
{
    return sys_get_temp_dir().'/sk-purge-sessions-'.getmypid();
}

/**
 * The real `web` stack on the FILE driver, plus routes that drive the action
 * the way ProfileController::destroySessions does.
 */
function posBootWebStack(): void
{
    File::ensureDirectoryExists(posSessionDir());
    config(['session.driver' => 'file', 'session.files' => posSessionDir()]);

    // Testbench syncs its kernel groups onto the router when the kernel is
    // first resolved, dropping what booted() appended. Resolve it first, then
    // attach — in a real app the groups exist before booted() runs.
    app(HttpKernel::class);
    posAttach();

    expect(Route::getMiddlewareGroups()['web'])->toContain(AuthenticateSession::class);

    Route::get('/login', fn () => 'login page')->name('login');

    Route::middleware('web')->group(function (): void {
        Route::get('/pos/login/{id}', function (Request $request, string $id) {
            Auth::login(PurgeSessionsUser::findOrFail($id), $request->boolean('remember'));

            return 'signed-in';
        });

        Route::get('/pos/whoami', fn () => Auth::check() ? 'user:'.Auth::id() : 'guest');

        Route::post('/pos/purge', function (Request $request, PurgeOtherSessionsAction $action) {
            $action->execute(
                $request->user(),
                (string) $request->input('password'),
                $request->session()->getId(),
            );

            return 'purged';
        });

        // The Users screen saving the signed-in user's own row: like the
        // route-bound `User $user`, a copy that is not the guard's instance.
        Route::post('/pos/update-own-password', function (Request $request, UpdateUserAction $action) {
            $copy = PurgeSessionsUser::findOrFail(Auth::id());

            $action->execute($copy, UserDTO::fromArray([
                'first_name' => $copy->first_name,
                'last_name' => $copy->last_name,
                'email' => $copy->email,
                'status' => $copy->status,
                'password' => (string) $request->input('password'),
            ]));

            return 'updated';
        });
    });

    Route::getRoutes()->refreshNameLookups();
}

/** A browser that signed in (SessionGuard::login() stamps the hash) and is confirmed authenticated. */
function posStampedBrowser(Testbench $test, PurgeSessionsUser $user, bool $remember = false): PosBrowser
{
    $browser = new PosBrowser($test);
    $browser->get('/pos/login/'.$user->getKey().($remember ? '?remember=1' : ''))->assertOk();
    $browser->get('/pos/whoami')->assertOk()->assertSee('user:'.$user->getKey());

    return $browser;
}

// Time is deliberately NOT frozen file-wide: cookie expiry and the file
// session handler's lifetime check both run against the clock.
beforeEach(function (): void {
    // Cookies and the cookie-for-password HMAC are encrypted with it.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    config(['activitylog.enabled' => false]);
    config(['auth.providers.users.model' => PurgeSessionsUser::class]);
    config(['session.driver' => 'database']);
    config(['session.connection' => null]);
    config(['session.table' => 'sessions']);

    // Mirrors stubs/database/migrations/0001_01_01_000000_create_users_table.php.
    Schema::create('purge_sessions_users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->timestamp('password_changed_at')->nullable();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->rememberToken();
        $table->string('status')->nullable()->default('active');
        $table->string('timezone', 64)->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->uuid('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
});

afterEach(function (): void {
    Carbon::setTestNow();
    File::deleteDirectory(posSessionDir());
});

// ──────────────────────────────────────────────────────────────────────────────
// 1. Action contract
// ──────────────────────────────────────────────────────────────────────────────

it('runs with a live saving hook — a real password change does move password_changed_at', function (): void {
    // Control: without this, "password_changed_at unchanged" below could pass
    // only because no hook exists.
    $user = posUser();
    Carbon::setTestNow('2026-10-04 12:00:00');

    $user->password = 'Another-Password-2!';
    $user->save();

    expect($user->fresh()->password_changed_at->toDateTimeString())->toBe('2026-10-04 12:00:00');
});

it('rejects a wrong password and changes nothing', function (): void {
    $user = posUser();
    $other = posUser('grace@example.test');
    $current = posSessionRow($user->getKey());
    $mine = posSessionRow($user->getKey());
    $theirs = posSessionRow($other->getKey());
    $events = posCaptureOtherDeviceLogout();

    $this->actingAs($user);

    expect(fn () => app(PurgeOtherSessionsAction::class)->execute($user, 'Wrong-Password-9!', $current))
        ->toThrow(ValidationException::class);

    $fresh = $user->fresh();

    expect($fresh->password)->toBe($user->password)
        ->and($fresh->getRememberToken())->toBe($user->getRememberToken())
        ->and($fresh->password_changed_at->toDateTimeString())->toBe(POS_PASSWORD_CHANGED_AT)
        ->and(DB::table('sessions')->whereIn('id', [$current, $mine, $theirs])->count())->toBe(3)
        ->and($events)->toHaveCount(0);
});

it('rehashes the same password, keeps the expiry clock, cycles the remember token and fires OtherDeviceLogout', function (string $driver): void {
    config(['session.driver' => $driver]);

    $user = posUser();
    $events = posCaptureOtherDeviceLogout();

    $before = $user->only(['password', 'remember_token']);

    $this->actingAs($user);

    app(PurgeOtherSessionsAction::class)->execute($user, POS_PASSWORD, Str::random(40));

    $fresh = $user->fresh();

    expect($fresh->password)->not->toBe($before['password'])
        ->and(Hash::check(POS_PASSWORD, $fresh->password))->toBeTrue()
        ->and($fresh->password_changed_at->toDateTimeString())->toBe(POS_PASSWORD_CHANGED_AT)
        ->and($fresh->getRememberToken())->not->toBeEmpty()
        ->and($fresh->getRememberToken())->not->toBe($before['remember_token'])
        ->and($events)->toHaveCount(1)
        ->and($events[0]->guard)->toBe('web')
        ->and($events[0]->user->getAuthIdentifier())->toBe($user->getKey());
})->with(['database', 'file', 'array', 'cookie']);

it('refuses to report success when the default guard cannot purge as this user', function (string $state): void {
    $user = posUser();
    $other = posUser('grace@example.test');
    $current = posSessionRow($user->getKey());
    $mine = posSessionRow($user->getKey());
    $events = posCaptureOtherDeviceLogout();

    match ($state) {
        'another user' => $this->actingAs($other),
        'a guest' => null,
        'a non-session guard' => (function () use ($user): void {
            Auth::viaRequest('pos-token', fn () => $user);
            config([
                'auth.guards.pos-token' => ['driver' => 'pos-token', 'provider' => 'users'],
                'auth.defaults.guard' => 'pos-token',
            ]);
        })(),
    };

    expect(fn () => app(PurgeOtherSessionsAction::class)->execute($user, POS_PASSWORD, $current))
        ->toThrow(LogicException::class);

    $fresh = $user->fresh();

    expect($fresh->password)->toBe($user->password)
        ->and($fresh->getRememberToken())->toBe($user->getRememberToken())
        ->and(DB::table('sessions')->whereIn('id', [$current, $mine])->count())->toBe(2)
        ->and($events)->toHaveCount(0);
})->with(['another user', 'a guest', 'a non-session guard']);

// ──────────────────────────────────────────────────────────────────────────────
// 2. `database` driver — immediate row delete
// ──────────────────────────────────────────────────────────────────────────────

it('deletes the user\'s other database rows and keeps the current row and every other owner\'s row', function (): void {
    $user = posUser();
    $other = posUser('grace@example.test');
    $current = posSessionRow($user->getKey());
    $laptop = posSessionRow($user->getKey());
    $phone = posSessionRow($user->getKey());
    $theirs = posSessionRow($other->getKey());
    $guest = posSessionRow(null);

    $this->actingAs($user);

    app(PurgeOtherSessionsAction::class)->execute($user, POS_PASSWORD, $current);

    expect(DB::table('sessions')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$current, $theirs, $guest])->sort()->values()->all())
        ->and(DB::table('sessions')->whereIn('id', [$laptop, $phone])->exists())->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Request level — file driver, so only AuthenticateSession can end a session
// ──────────────────────────────────────────────────────────────────────────────

it('logs another device out on its next request and leaves it a guest', function (): void {
    posBootWebStack();
    $user = posUser();
    $current = posStampedBrowser($this, $user);
    $elsewhere = posStampedBrowser($this, $user);

    $current->post('/pos/purge', ['password' => POS_PASSWORD])->assertOk()->assertSee('purged');

    $elsewhere->get('/pos/whoami')->assertRedirect('/login');
    $elsewhere->get('/pos/whoami')->assertOk()->assertSee('guest');
});

it('keeps the purging device signed in', function (): void {
    posBootWebStack();
    $user = posUser();
    $current = posStampedBrowser($this, $user);
    posStampedBrowser($this, $user);

    $current->post('/pos/purge', ['password' => POS_PASSWORD])->assertOk();

    $current->get('/pos/whoami')->assertOk()->assertSee('user:'.$user->getKey());
    $current->get('/pos/whoami')->assertOk()->assertSee('user:'.$user->getKey());
});

it('keeps the purging device\'s remember-me cookie working', function (): void {
    posBootWebStack();
    $user = posUser();
    $current = posStampedBrowser($this, $user, remember: true);
    $oldRecaller = $current->cookies[posRecallerName()] ?? null;

    $current->post('/pos/purge', ['password' => POS_PASSWORD])->assertOk();

    $newRecaller = $current->cookies[posRecallerName()] ?? null;

    expect($oldRecaller)->not->toBeNull()
        ->and($newRecaller)->not->toBeNull()
        ->and($newRecaller)->not->toBe($oldRecaller);

    // The session expired; only the re-queued remember-me cookie is left.
    $cookieOnly = new PosBrowser($this);
    $cookieOnly->cookies = [posRecallerName() => $newRecaller];

    $cookieOnly->get('/pos/whoami')->assertOk()->assertSee('user:'.$user->getKey());
});

it('stops a remember-me cookie carrying the old token from authenticating', function (): void {
    posBootWebStack();
    $user = posUser();
    $elsewhere = new PosBrowser($this);
    $elsewhere->get('/pos/login/'.$user->getKey().'?remember=1')->assertOk();
    $oldRecaller = $elsewhere->cookies[posRecallerName()] ?? null;

    expect($oldRecaller)->not->toBeNull();

    // Control: before the purge that cookie alone signs the device in.
    $before = new PosBrowser($this);
    $before->cookies = [posRecallerName() => $oldRecaller];
    $before->get('/pos/whoami')->assertOk()->assertSee('user:'.$user->getKey());

    posStampedBrowser($this, $user)->post('/pos/purge', ['password' => POS_PASSWORD])->assertOk();

    // The cookie as the device holds it: old token, old hash.
    $stale = new PosBrowser($this);
    $stale->cookies = [posRecallerName() => $oldRecaller];
    $stale->get('/pos/whoami')->assertOk()->assertSee('guest');

    // Old token with the CURRENT hash — isolates the token cycle from the hash check.
    [$id, $oldToken] = explode('|', $oldRecaller);
    $forged = new PosBrowser($this);
    $forged->cookies = [
        posRecallerName() => $id.'|'.$oldToken.'|'.Auth::guard('web')->hashPasswordForCookie($user->fresh()->password),
    ];
    $forged->get('/pos/whoami')->assertOk()->assertSee('guest');
});

it('does not log out a session opened before the upgrade that carries no stored hash', function (): void {
    posBootWebStack();
    $user = posUser();

    // SessionGuard::login() stamps the hash itself, so strip it from the
    // stored payload: a signed-in session without `password_hash_web` is
    // exactly what a session opened before AuthenticateSession was attached
    // looks like.
    $legacy = new PosBrowser($this);
    $legacy->get('/pos/login/'.$user->getKey())->assertOk();
    $path = posSessionDir().'/'.$legacy->cookies[config('session.cookie')];
    $payload = unserialize(file_get_contents($path));

    expect($payload)->toHaveKey('password_hash_web');

    unset($payload['password_hash_web']);
    file_put_contents($path, serialize($payload));

    posStampedBrowser($this, $user)->post('/pos/purge', ['password' => POS_PASSWORD])->assertOk();

    $legacy->get('/pos/whoami')->assertOk()->assertSee('user:'.$user->getKey());
});

it('keeps a user signed in after changing their own password on the Users screen', function (): void {
    // UpdateUserAction snapshots the persisted role set on every change.
    config(['permission' => require dirname(__DIR__, 3).'/vendor/spatie/laravel-permission/config/permission.php']);
    app()->forgetInstance(PermissionRegistrar::class);

    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->uuid('model_id');
    });

    posBootWebStack();
    $user = posUser();
    $current = posStampedBrowser($this, $user);
    $elsewhere = posStampedBrowser($this, $user);

    $current->post('/pos/update-own-password', ['password' => 'Another-Password-2!'])->assertOk();

    $current->get('/pos/whoami')->assertOk()->assertSee('user:'.$user->getKey());
    $elsewhere->get('/pos/whoami')->assertRedirect('/login');
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. Wiring — defensive append to `web`
// ──────────────────────────────────────────────────────────────────────────────

it('appends AuthenticateSession to the end of the web group only', function (): void {
    posSetGroups(['web' => [StartSession::class], 'api' => ['throttle:api']]);

    posAttach();

    expect(Route::getMiddlewareGroups())->toBe([
        'web' => [StartSession::class, AuthenticateSession::class],
        'api' => ['throttle:api'],
    ]);
});

it('never invents a web group the app does not define', function (): void {
    posSetGroups(['api' => ['throttle:api']]);

    posAttach();

    expect(Route::getMiddlewareGroups())->toBe(['api' => ['throttle:api']]);
});

it('does not double-register when the web group already carries it', function (string $entry): void {
    posSetGroups(['web' => [StartSession::class, $entry]]);

    posAttach();

    expect(Route::getMiddlewareGroups()['web'])->toBe([StartSession::class, $entry]);
})->with([
    'by class' => AuthenticateSession::class,
    'by alias' => 'auth.session',
]);

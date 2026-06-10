<?php

/*
|--------------------------------------------------------------------------
| Cross-page "select all filtered" bulk selection — query scope + allow-list
|--------------------------------------------------------------------------
|
| Bu testler Task 4'ün KRİTİK güvenlik garantilerini doğrular:
|
|   A) UserDatatableQuery::scopeByHierarchy() — rol-hiyerarşi görünürlük
|      scope'unun builder çekirdeği. Datatable listesi (applyVisibilityScope)
|      ile cross-page bulk re-query TEK kaynaktan (bu metot) gelir; bu yüzden
|      burada DB üzerinden doğrulanır:
|        - system_admin → tüm kullanıcılar görünür
|        - rütbeli aktör → yalnız eşit/alt rütbe (sort_order >= aktör min)
|        - rolsüz aktör (direct-permission) → yalnız rolsüz kullanıcılar
|
|   B) UserBulkSelectionQuery::normalizeFilters() — allow-list parse. Keyfi
|      snapshot anahtarı query'ye sızmamalı; yalnız status/role/search.
|      bracket-style ('filter[status]') ve nested (['filter']['status'])
|      şekilleri kabul edilir; App\Models gerektirmeyen saf mantık.
|
|   C) RoleBulkSelectionQuery::extractSearch() — yalnız 'search' allow-list'li.
|
| App\Models\User/Role bu pakette autoload edilemediğinden resolve()'ın
| DB tarafı (User::query()) burada test edilemez; bunun yerine güvenliği
| belirleyen iki parça izole edilir: (A) scope mantığı yerel bir test modeli
| üzerinden Builder ile, (B/C) allow-list parse reflection ile.
|
*/

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Domain\Role\Queries\RoleBulkSelectionQuery;
use Lvntr\StarterKit\Domain\User\Queries\UserBulkSelectionQuery;
use Lvntr\StarterKit\Domain\User\Queries\UserDatatableQuery;

// ──────────────────────────────────────────────────────────────────────────────
// Yerel test modelleri — App namespace gerektirmez; gerçek roles + pivot şeması
// ──────────────────────────────────────────────────────────────────────────────

class BulkScopeTestRole extends Model
{
    protected $table = 'bulk_scope_roles';

    protected $guarded = [];

    public $timestamps = false;
}

class BulkScopeTestUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            BulkScopeTestRole::class,
            'bulk_scope_user_role',
            'user_id',
            'role_id',
        );
    }
}

beforeEach(function (): void {
    Schema::create('bulk_scope_roles', function ($table): void {
        $table->id();
        $table->string('name');
        $table->integer('sort_order');
    });

    Schema::create('bulk_scope_user_role', function ($table): void {
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('role_id');
    });
});

afterEach(function (): void {
    Schema::dropIfExists('bulk_scope_user_role');
    Schema::dropIfExists('bulk_scope_roles');
});

/**
 * Helper: create a user row (minimal users schema) and optionally attach roles.
 *
 * @param  int[]  $roleIds
 */
function makeScopedUser(string $email, array $roleIds = []): BulkScopeTestUser
{
    $user = new BulkScopeTestUser;
    $user->forceFill([
        'name' => $email,
        'email' => $email,
        'password' => 'x',
    ])->save();

    if ($roleIds !== []) {
        $user->roles()->attach($roleIds);
    }

    return $user;
}

// ──────────────────────────────────────────────────────────────────────────────
// A) scopeByHierarchy — rol hiyerarşi görünürlük scope'unun builder çekirdeği
// ──────────────────────────────────────────────────────────────────────────────

it('system_admin actor sees every user (scope is a no-op)', function (): void {
    $admin = BulkScopeTestRole::create(['name' => 'admin', 'sort_order' => 1]);
    $user = BulkScopeTestRole::create(['name' => 'user', 'sort_order' => 10]);

    makeScopedUser('a@x.test', [$admin->id]);
    makeScopedUser('b@x.test', [$user->id]);
    makeScopedUser('c@x.test', []);

    $visible = UserDatatableQuery::scopeByHierarchy(
        BulkScopeTestUser::query(),
        true,   // isSystemAdmin
        1,      // minSortOrder (yok sayılır)
    )->pluck('email')->all();

    expect($visible)->toHaveCount(3); // herkes görünür
});

it('ranked actor only sees equal/lower rank users (sort_order >= actor min)', function (): void {
    $admin = BulkScopeTestRole::create(['name' => 'admin', 'sort_order' => 5]);
    $user = BulkScopeTestRole::create(['name' => 'user', 'sort_order' => 10]);

    makeScopedUser('higher@x.test', [$admin->id]);   // sort_order 5 < actor 10 → GİZLİ
    makeScopedUser('equal@x.test', [$user->id]);     // 10 == 10 → görünür
    makeScopedUser('roleless@x.test', []);           // rolsüz → görünür

    $visible = UserDatatableQuery::scopeByHierarchy(
        BulkScopeTestUser::query(),
        false,  // isSystemAdmin
        10,     // actor min sort_order
    )->pluck('email')->sort()->values()->all();

    expect($visible)->toBe(['equal@x.test', 'roleless@x.test']);
});

it('role-less actor (direct-permission) only sees other role-less users', function (): void {
    $admin = BulkScopeTestRole::create(['name' => 'admin', 'sort_order' => 5]);

    makeScopedUser('ranked@x.test', [$admin->id]); // rütbeli → GİZLİ
    makeScopedUser('roleless@x.test', []);         // rolsüz → görünür

    $visible = UserDatatableQuery::scopeByHierarchy(
        BulkScopeTestUser::query(),
        false,  // isSystemAdmin
        null,   // actor min sort_order = null (rolsüz)
    )->pluck('email')->sort()->values()->all();

    expect($visible)->toBe(['roleless@x.test']);
});

// ──────────────────────────────────────────────────────────────────────────────
// B) UserBulkSelectionQuery::normalizeFilters — allow-list parse (saf mantık)
// ──────────────────────────────────────────────────────────────────────────────

function normalizeUserFilters(array $snapshot): array
{
    $q = new UserBulkSelectionQuery;
    $ref = new ReflectionMethod($q, 'normalizeFilters');
    $ref->setAccessible(true);

    return $ref->invoke($q, $snapshot);
}

it('parses bracket-style filter keys and keeps only allow-listed ones', function (): void {
    $result = normalizeUserFilters([
        'filter[status]' => 'active',
        'filter[role]' => 'admin',
        'filter[search]' => 'john',
        'filter[evil]' => 'DROP',     // allow-list dışı → düşmeli
        'sort' => '-created_at',       // filter değil → yok sayılmalı
        'page' => '3',
        'columns' => 'name,email',
    ]);

    expect($result)->toBe([
        'status' => 'active',
        'role' => 'admin',
        'search' => 'john',
    ]);
});

it('parses the nested filter shape too', function (): void {
    $result = normalizeUserFilters([
        'filter' => ['status' => 'inactive', 'role' => 'user', 'injected' => 'x'],
    ]);

    expect($result)->toBe([
        'status' => 'inactive',
        'role' => 'user',
    ]);
});

it('drops empty / non-scalar filter values', function (): void {
    $result = normalizeUserFilters([
        'filter[status]' => '   ',          // boş (trim) → düşmeli
        'filter[role]' => ['array'],         // non-scalar → düşmeli
        'filter[search]' => 'kept',
    ]);

    expect($result)->toBe(['search' => 'kept']);
});

it('returns an empty filter set for an arbitrary / hostile snapshot', function (): void {
    $result = normalizeUserFilters([
        'filter[deleted_at]' => 'whatever',
        'password' => 'x',
        'is_admin' => '1',
    ]);

    expect($result)->toBe([]);
});

// ──────────────────────────────────────────────────────────────────────────────
// C) RoleBulkSelectionQuery::extractSearch — yalnız search allow-list'li
// ──────────────────────────────────────────────────────────────────────────────

function extractRoleSearch(array $snapshot): ?string
{
    $q = new RoleBulkSelectionQuery;
    $ref = new ReflectionMethod($q, 'extractSearch');
    $ref->setAccessible(true);

    return $ref->invoke($q, $snapshot);
}

it('extracts the role search term (bracket + nested), ignoring other keys', function (): void {
    expect(extractRoleSearch(['filter[search]' => 'manager']))->toBe('manager');
    expect(extractRoleSearch(['filter' => ['search' => 'editor']]))->toBe('editor');
    expect(extractRoleSearch(['filter[name]' => 'x', 'filter[evil]' => 'y']))->toBeNull();
    expect(extractRoleSearch(['filter[search]' => '   ']))->toBeNull();
});

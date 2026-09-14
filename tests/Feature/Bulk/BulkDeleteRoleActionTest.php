<?php

/*
|--------------------------------------------------------------------------
| BulkDeleteRoleAction authorization tests
|--------------------------------------------------------------------------
|
| The invariant under test: POST /admin/roles/bulk must never be a wider door
| than DELETE /admin/roles/{role}. An equal-rank role is deletable by NEITHER.
|
| The shipped stub (stubs/app/Domain/Role/BulkActions/BulkDeleteRoleAction.php)
| cannot be instantiated from this suite. `App\` is not autoloaded, and the
| moment App\Enums\RoleEnum is require_once'd into the process the previously
| dormant StarterKitServiceProvider::configureGates() starts registering a
| Gate::before closure typed to App\Models\User — which then TypeErrors in every
| other test that authorizes an anonymous Authorizable (44 failures, measured).
| So the rule is covered from two sides:
|
|   1. Behaviour — an in-test mirror of authorize() pins the expected outcomes
|      (permission gate, protected system roles, rank hierarchy).
|   2. Source contract — the tests at the bottom assert the shipped action keeps
|      NO rank rule of its own and delegates to CanManageRoleQuery, and that the
|      query ranks STRICTLY. Without (2) the mirror in (1) is worthless: the
|      previous version of this file hard-coded `>=` and kept passing while the
|      shipped action drifted away from the singular destroy() path.
|
*/

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Http\Bulk\BulkAction;
use Lvntr\StarterKit\Http\Bulk\BulkActionDispatcher;
use Lvntr\StarterKit\Http\Bulk\BulkDeleteAction;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function skRepoPath(string $relative): string
{
    return dirname(__DIR__, 3).'/'.$relative;
}

function makeRoleActor(
    int $id,
    bool $canDelete = true,
    bool $isSystemAdmin = false,
    ?int $sortOrder = 10,
): Authenticatable {
    $user = Mockery::mock(Authenticatable::class);
    $user->id = $id;
    $user->shouldReceive('getAuthIdentifier')->andReturn($id);
    $user->shouldReceive('can')->with('roles.delete')->andReturn($canDelete);
    $user->shouldReceive('hasRole')->andReturn($isSystemAdmin);

    $roles = Mockery::mock(Collection::class);
    $roles->shouldReceive('min')->with('sort_order')->andReturn($sortOrder);
    $user->roles = $roles;

    return $user;
}

function makeRoleModel(int $id, string $name, int $sortOrder = 10): Model
{
    $model = Mockery::mock(Model::class)->makePartial();
    $model->id = $id;
    $model->name = $name;
    $model->sort_order = $sortOrder;

    return $model;
}

// System role names (stand-in for the real RoleEnum, which must not be loaded
// into this process — see the file header).
function systemRoleNames(): array
{
    return ['system_admin', 'admin', 'user'];
}

/**
 * Mirror of CanManageRoleQuery::check(). STRICT `>` on purpose: an equal-rank
 * role is NOT manageable. Pinned to the real query by the source-contract test
 * 'CanManageRoleQuery ranks strictly'.
 */
function canManageRoleRank(Authenticatable $actor, Model $role): bool
{
    if ($actor->hasRole('system_admin')) {
        return true;
    }

    $actorMinSortOrder = $actor->roles->min('sort_order');

    // Role-less actor — casting null → 0 would let them manage every role.
    if ($actorMinSortOrder === null) {
        return false;
    }

    return $role->sort_order > (int) $actorMinSortOrder;
}

/**
 * Mirror of the shipped authorize(): permission gate, protected system roles,
 * and the rank decision delegated to the query rule — no second rank rule.
 */
function makeRoleBulkDeleteAction(): BulkDeleteAction
{
    $protectedRoles = systemRoleNames();

    return new class($protectedRoles) extends BulkDeleteAction
    {
        /** @param string[] $protectedRoles */
        public function __construct(private array $protectedRoles) {}

        public function authorize(Authenticatable $user, Collection $items): Collection
        {
            if (! $user->can('roles.delete')) {
                return new Collection;
            }

            return $items->filter(function (Model $role) use ($user): bool {
                // System roles are always protected — even from system_admin.
                if (in_array($role->name, $this->protectedRoles, true)) {
                    return false;
                }

                return canManageRoleRank($user, $role);
            })->values();
        }
    };
}

/**
 * @param  array<int, Model>  $roles
 * @return array<int, int>
 */
function authorizedRoleIds(array $roles, Authenticatable $actor): array
{
    return makeRoleBulkDeleteAction()
        ->authorize($actor, new Collection($roles))
        ->pluck('id')
        ->all();
}

// ──────────────────────────────────────────────────────────────────────────────
// Tests
// ──────────────────────────────────────────────────────────────────────────────

it('returns an empty collection for an actor without roles.delete', function (): void {
    $actor = makeRoleActor(id: 1, canDelete: false);
    $role = makeRoleModel(id: 10, name: 'editor', sortOrder: 50);

    expect(authorizedRoleIds([$role], $actor))->toBe([]);
});

it('always protects system roles, even from system_admin', function (): void {
    $actor = makeRoleActor(id: 1, isSystemAdmin: true);

    $sysRole1 = makeRoleModel(id: 1, name: 'system_admin', sortOrder: 1);
    $sysRole2 = makeRoleModel(id: 2, name: 'admin', sortOrder: 2);
    $sysRole3 = makeRoleModel(id: 3, name: 'user', sortOrder: 3);
    $customRole = makeRoleModel(id: 4, name: 'editor', sortOrder: 20);

    expect(authorizedRoleIds([$sysRole1, $sysRole2, $sysRole3, $customRole], $actor))->toBe([4]);
});

it('lets system_admin delete every non-system role', function (): void {
    $actor = makeRoleActor(id: 1, isSystemAdmin: true);

    $roles = collect(range(1, 5))
        ->map(fn (int $i) => makeRoleModel($i, "custom-role-{$i}", $i * 10))
        ->all();

    expect(authorizedRoleIds($roles, $actor))->toBe([1, 2, 3, 4, 5]);
});

it('denies a role that outranks the actor', function (): void {
    $actor = makeRoleActor(id: 1, sortOrder: 10);

    $highRank = makeRoleModel(id: 1, name: 'senior-editor', sortOrder: 5);
    $lowRank = makeRoleModel(id: 2, name: 'viewer', sortOrder: 20);

    expect(authorizedRoleIds([$highRank, $lowRank], $actor))->toBe([2]);
});

it('denies an equal-rank custom role — bulk may not outrun the singular destroy() path', function (): void {
    // Regression: authorize() carried its own `>=` while CanManageRoleQuery —
    // the query destroy()/edit()/data() run — uses `>`. A roles.delete actor
    // could therefore bulk-delete the peer role that DELETE
    // /admin/roles/{role} refuses with a 403.
    $actor = makeRoleActor(id: 1, sortOrder: 10);
    $equalRank = makeRoleModel(id: 7, name: 'editor', sortOrder: 10);

    expect(authorizedRoleIds([$equalRank], $actor))->toBe([])
        ->and(canManageRoleRank($actor, $equalRank))->toBeFalse();
});

it('mixed payload: deletes only the role ranked below the actor', function (): void {
    $actor = makeRoleActor(id: 1, sortOrder: 10);

    $equalRank = makeRoleModel(id: 1, name: 'editor', sortOrder: 10);
    $lowRank = makeRoleModel(id: 2, name: 'viewer', sortOrder: 11);

    expect(authorizedRoleIds([$equalRank, $lowRank], $actor))->toBe([2]);
});

it('denies everything for a role-less (direct-permission) actor', function (): void {
    $actor = makeRoleActor(id: 1, sortOrder: null);

    $customRole = makeRoleModel(id: 3, name: 'editor', sortOrder: 20);

    expect(authorizedRoleIds([$customRole], $actor))->toBe([]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Source contract — what keeps the mirror above honest
// ──────────────────────────────────────────────────────────────────────────────

it('the shipped BulkDeleteRoleAction keeps no rank rule of its own', function (): void {
    $source = (string) file_get_contents(
        skRepoPath('stubs/app/Domain/Role/BulkActions/BulkDeleteRoleAction.php')
    );

    $inlineRankRule = '/sort_order\s*[<>]=?/';

    expect($source)
        ->toContain('use App\Domain\Role\Queries\CanManageRoleQuery;')
        ->toContain('$this->canManageQuery->check($user, $role)')
        // A second, inline rank comparison is exactly what produced the
        // `>=` (bulk) vs `>` (singular) split — there must be none left.
        ->not->toMatch($inlineRankRule)
        // ...and the guard above is only worth anything if it really matches
        // the line this regression shipped with:
        ->and('(int) $role->sort_order >= (int) $actorMinSortOrder;')->toMatch($inlineRankRule);
});

it('CanManageRoleQuery ranks strictly — an equal-rank role is not manageable', function (): void {
    $source = (string) file_get_contents(
        skRepoPath('src/Domain/Role/Queries/CanManageRoleQuery.php')
    );

    expect($source)
        ->toContain('return $role->sort_order > (int) $userMinSortOrder;')
        ->not->toContain('sort_order >=');
});

it('RoleController runs the singular delete through the same query', function (): void {
    $source = (string) file_get_contents(
        skRepoPath('stubs/app/Http/Controllers/Admin/RoleController.php')
    );

    expect($source)
        ->toContain('public function destroy(Role $role, DeleteRoleAction $action, CanManageRoleQuery $canManageQuery)')
        ->toContain('if (! $canManageQuery->check(Auth::user(), $role)) {');
});

it('dispatcher: a throwing handle() propagates (transaction rollback simulation)', function (): void {
    $dispatcher = new BulkActionDispatcher;

    $throwingAction = new class implements BulkAction
    {
        public function getKey(): string
        {
            return 'delete';
        }

        public function authorize(Authenticatable $user, Collection $items): Collection
        {
            return $items;
        }

        public function handle(Collection $items): array
        {
            throw new RuntimeException('Simulated DB constraint violation');
        }
    };

    $dispatcher->register($throwingAction);

    $actor = makeRoleActor(id: 1);
    $role = makeRoleModel(id: 10, name: 'custom', sortOrder: 20);

    $items = new Collection([$role]);

    expect(fn () => $dispatcher->dispatch($actor, 'delete', $items))
        ->toThrow(RuntimeException::class, 'Simulated DB constraint violation');
});

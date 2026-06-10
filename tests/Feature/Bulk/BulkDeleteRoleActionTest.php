<?php

/*
|--------------------------------------------------------------------------
| BulkDeleteRoleAction Authorize Mantık Testleri
|--------------------------------------------------------------------------
|
| BulkDeleteRoleAction App namespace'indedir; mantığı in-test override ile simüle edilir.
|
| Test senaryoları:
|   - roles.delete permission'ı olmayan actor → boş koleksiyon
|   - Sistem rolleri korunur (protected name listesi)
|   - system_admin tüm non-sistem rolleri silebilir
|   - Non-system_admin kendi rütbesinden yüksek rolleri silemez
|   - Dispatcher aracılığıyla exception propagation (transaction rollback simülasyonu)
|
*/

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Http\Bulk\BulkAction;
use Lvntr\StarterKit\Http\Bulk\BulkActionDispatcher;
use Lvntr\StarterKit\Http\Bulk\BulkDeleteAction;

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcılar
// ──────────────────────────────────────────────────────────────────────────────

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

// Sistem role adları (gerçek RoleEnum yerine sabit liste)
function systemRoleNames(): array
{
    return ['system_admin', 'admin'];
}

function makeRoleBulkDeleteAction(): BulkDeleteAction
{
    $protectedRoles = systemRoleNames();

    return new class($protectedRoles) extends BulkDeleteAction
    {
        private array $protectedRoles;

        public function __construct(array $protectedRoles)
        {
            $this->protectedRoles = $protectedRoles;
        }

        public function authorize(Authenticatable $user, Collection $items): Collection
        {
            if (! $user->can('roles.delete')) {
                return new Collection;
            }

            $isSystemAdmin = $user->hasRole('system_admin');
            $actorMinSortOrder = $isSystemAdmin ? null : $user->roles->min('sort_order');

            return $items->filter(function ($role) use ($isSystemAdmin, $actorMinSortOrder): bool {
                // Sistem rolleri her zaman korunur
                if (in_array($role->name, $this->protectedRoles, true)) {
                    return false;
                }

                if ($isSystemAdmin) {
                    return true;
                }

                // Rolsüz actor — en düşük rütbe, hiçbir rolü silemez
                if ($actorMinSortOrder === null) {
                    return false;
                }

                return (int) $role->sort_order >= (int) $actorMinSortOrder;
            })->values();
        }
    };
}

// ──────────────────────────────────────────────────────────────────────────────
// Testler
// ──────────────────────────────────────────────────────────────────────────────

it('roles.delete permission\'ı olmayan actor → boş koleksiyon', function (): void {
    $action = makeRoleBulkDeleteAction();
    $actor = makeRoleActor(id: 1, canDelete: false);
    $role = makeRoleModel(id: 10, name: 'editor');

    $result = $action->authorize($actor, new Collection([$role]));

    expect($result)->toBeEmpty();
});

it('sistem rolleri her zaman korunur', function (): void {
    $action = makeRoleBulkDeleteAction();
    $actor = makeRoleActor(id: 1, isSystemAdmin: true);

    $sysRole1 = makeRoleModel(id: 1, name: 'system_admin', sortOrder: 1);
    $sysRole2 = makeRoleModel(id: 2, name: 'admin', sortOrder: 2);
    $customRole = makeRoleModel(id: 3, name: 'editor', sortOrder: 20);

    $result = $action->authorize($actor, new Collection([$sysRole1, $sysRole2, $customRole]));

    expect($result->count())->toBe(1)
        ->and($result->first()->name)->toBe('editor');
});

it('system_admin tüm non-sistem rolleri silebilir', function (): void {
    $action = makeRoleBulkDeleteAction();
    $actor = makeRoleActor(id: 1, isSystemAdmin: true);

    $roles = collect(range(1, 5))
        ->map(fn (int $i) => makeRoleModel($i, "custom-role-{$i}", $i * 10))
        ->all();

    $result = $action->authorize($actor, new Collection($roles));

    expect($result->count())->toBe(5);
});

it('non-system_admin kendi rütbesinden yüksek rolleri silemez', function (): void {
    $action = makeRoleBulkDeleteAction();
    $actor = makeRoleActor(id: 1, isSystemAdmin: false, sortOrder: 10);

    $highRank = makeRoleModel(id: 1, name: 'senior-editor', sortOrder: 5);  // yüksek rütbe → atla
    $equalRank = makeRoleModel(id: 2, name: 'editor', sortOrder: 10);        // eşit → geçir
    $lowRank = makeRoleModel(id: 3, name: 'viewer', sortOrder: 20);          // düşük → geçir

    $result = $action->authorize($actor, new Collection([$highRank, $equalRank, $lowRank]));

    expect($result->count())->toBe(2)
        ->and($result->pluck('name')->all())->toContain('editor')
        ->and($result->pluck('name')->all())->toContain('viewer')
        ->and($result->pluck('name')->all())->not->toContain('senior-editor');
});

it('rolsüz actor (direct-permission) → hiçbir rolü silemez', function (): void {
    $action = makeRoleBulkDeleteAction();
    $actor = makeRoleActor(id: 1, isSystemAdmin: false, sortOrder: null);

    $customRole = makeRoleModel(id: 3, name: 'editor', sortOrder: 20);

    $result = $action->authorize($actor, new Collection([$customRole]));

    expect($result)->toBeEmpty();
});

it('dispatcher: handle exception → exception yayılır (transaction rollback simülasyonu)', function (): void {
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

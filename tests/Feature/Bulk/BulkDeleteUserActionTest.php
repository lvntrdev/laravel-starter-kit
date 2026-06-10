<?php

/*
|--------------------------------------------------------------------------
| BulkDeleteUserAction Authorize Mantık Testleri
|--------------------------------------------------------------------------
|
| Bu testler BulkDeleteUserAction'ın authorize() mantığını doğrular.
| App\Domain\User\BulkActions\BulkDeleteUserAction stubs altında (app-owned;
| app-owned App\Http\BulkActions\BulkDeleteAction'ı extend ettiği için Faz 6'da
| vendor'a taşınmadı) olduğundan autoload edilemez; mantık package'in base
| sınıfları üzerinden in-test anonymous class ile simüle edilir.
|
| Test senaryoları:
|
|   A) BulkDeleteAction (generic base) — package src/ altında testable:
|      - Yetkisiz kullanıcı boş koleksiyon döner (override ile)
|      - Self-delete guard: actor kendisini listede görse atlanır
|      - Rank hiyerarşisi: actor hedefi outrank etmeli
|      - 100 item'lı bulk senaryosu
|
|   B) BulkDeleteAction handle (generic):
|      - per-item exception tüm batch'i durdurmaz (önceki testte kapsanıyor)
|
*/

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Http\Bulk\BulkDeleteAction;

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcılar — Testbench + Mockery ile çalışır (App namespace gerektirmez)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Sahte kullanıcı için Authenticatable mock.
 */
function makeUserActor(
    int $id,
    bool $canDelete = true,
    bool $isSystemAdmin = false,
    ?int $sortOrder = 10,
): Authenticatable {
    $user = Mockery::mock(Authenticatable::class);
    $user->id = $id;
    $user->shouldReceive('getAuthIdentifier')->andReturn($id);
    $user->shouldReceive('can')->with('users.delete')->andReturn($canDelete);
    $user->shouldReceive('hasRole')->andReturn($isSystemAdmin);

    // Roles relation simülasyonu
    $roles = Mockery::mock(Collection::class);
    $roles->shouldReceive('min')->with('sort_order')->andReturn($sortOrder);
    $user->roles = $roles;

    return $user;
}

/**
 * Sahte hedef Eloquent model — User benzeri, id + roles ile.
 */
function makeUserTarget(int $id, ?int $sortOrder = 10): Model
{
    $model = Mockery::mock(Model::class)->makePartial();
    $model->id = $id;

    $roles = Mockery::mock(Collection::class);
    $roles->shouldReceive('min')->with('sort_order')->andReturn($sortOrder);
    $model->roles = $roles;

    return $model;
}

// ──────────────────────────────────────────────────────────────────────────────
// Domain-aware BulkDeleteAction override (authorize mantığını inline test eder)
// Gerçek BulkDeleteUserAction App namespace'inde; mantığı burada in-test simüle ediyoruz
// ──────────────────────────────────────────────────────────────────────────────

function makeUserBulkDeleteAction(): BulkDeleteAction
{
    return new class extends BulkDeleteAction
    {
        public function authorize(Authenticatable $user, Collection $items): Collection
        {
            // users.delete permission kontrolü
            if (! $user->can('users.delete')) {
                return new Collection;
            }

            $isSystemAdmin = $user->hasRole('system_admin');
            $actorMinSortOrder = $isSystemAdmin ? null : $user->roles->min('sort_order');
            $actorId = $user->getAuthIdentifier();

            return $items->filter(function ($target) use ($actorId, $isSystemAdmin, $actorMinSortOrder): bool {
                // Self-delete guard
                if ($target->id === $actorId) {
                    return false;
                }

                if ($isSystemAdmin) {
                    return true;
                }

                // Rolsüz actor — en düşük rütbe, kimseyi outrank edemez
                if ($actorMinSortOrder === null) {
                    return false;
                }

                // Rank hiyerarşisi
                $targetMinSortOrder = $target->roles->min('sort_order');

                if ($targetMinSortOrder === null) {
                    return true;
                }

                return (int) $actorMinSortOrder <= (int) $targetMinSortOrder;
            })->values();
        }
    };
}

// ──────────────────────────────────────────────────────────────────────────────
// A) Authorize testleri
// ──────────────────────────────────────────────────────────────────────────────

it('users.delete permission\'ı olmayan actor → boş koleksiyon döner', function (): void {
    $action = makeUserBulkDeleteAction();
    $actor = makeUserActor(id: 1, canDelete: false);
    $target = makeUserTarget(id: 2);

    $result = $action->authorize($actor, new Collection([$target]));

    expect($result)->toBeEmpty();
});

it('actor kendi id\'sini listede gönderse bile atlanır (self-delete guard)', function (): void {
    $action = makeUserBulkDeleteAction();
    $actor = makeUserActor(id: 1, canDelete: true, isSystemAdmin: true);

    $selfTarget = makeUserTarget(id: 1); // actor kendisi
    $otherTarget = makeUserTarget(id: 2);

    $result = $action->authorize($actor, new Collection([$selfTarget, $otherTarget]));

    expect($result->count())->toBe(1)
        ->and($result->first()->id)->toBe(2);
});

it('system_admin kendisi hariç tüm user\'ları silebilir', function (): void {
    $action = makeUserBulkDeleteAction();
    $actorId = 99;
    $actor = makeUserActor(id: $actorId, canDelete: true, isSystemAdmin: true);

    $targets = collect([2, 3, 4])->map(fn (int $id) => makeUserTarget($id, 1));
    $result = $action->authorize($actor, new Collection($targets->all()));

    expect($result->count())->toBe(3);
});

it('actor sort_order=10 olan hedef sort_order=5\'i (daha yüksek rütbe) silemez', function (): void {
    $action = makeUserBulkDeleteAction();
    $actor = makeUserActor(id: 1, canDelete: true, isSystemAdmin: false, sortOrder: 10);

    $highRank = makeUserTarget(id: 2, sortOrder: 5);   // daha yüksek rütbe → atla
    $equalRank = makeUserTarget(id: 3, sortOrder: 10); // eşit → geçir
    $lowRank = makeUserTarget(id: 4, sortOrder: 20);   // daha düşük rütbe → geçir

    $result = $action->authorize($actor, new Collection([$highRank, $equalRank, $lowRank]));

    expect($result->count())->toBe(2)
        ->and($result->pluck('id')->all())->toEqual([3, 4]);
});

it('rolsüz target → actor silebilir (sort_order null = en düşük rütbe)', function (): void {
    $action = makeUserBulkDeleteAction();
    $actor = makeUserActor(id: 1, canDelete: true, isSystemAdmin: false, sortOrder: 10);

    $roleless = makeUserTarget(id: 2, sortOrder: null);
    $result = $action->authorize($actor, new Collection([$roleless]));

    expect($result->count())->toBe(1);
});

it('rolsüz actor (direct-permission) → hiçbir hedefi silemez', function (): void {
    $action = makeUserBulkDeleteAction();
    $actor = makeUserActor(id: 1, canDelete: true, isSystemAdmin: false, sortOrder: null);

    $ranked = makeUserTarget(id: 2, sortOrder: 5);
    $roleless = makeUserTarget(id: 3, sortOrder: null);

    $result = $action->authorize($actor, new Collection([$ranked, $roleless]));

    expect($result)->toBeEmpty();
});

it('100 item\'lık listede actor kendi id\'si atlanır', function (): void {
    $action = makeUserBulkDeleteAction();
    $actorId = 50;
    $actor = makeUserActor(id: $actorId, canDelete: true, isSystemAdmin: true);

    $items = collect(range(1, 100))->map(fn (int $id) => makeUserTarget($id))->all();

    $result = $action->authorize($actor, new Collection($items));

    expect($result->count())->toBe(99)
        ->and($result->pluck('id')->contains($actorId))->toBeFalse();
});

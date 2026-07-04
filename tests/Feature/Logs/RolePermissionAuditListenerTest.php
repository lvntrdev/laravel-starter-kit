<?php

/*
|--------------------------------------------------------------------------
| Role↔permission audit sink — LogRoleUpdated writes to activity_log
|--------------------------------------------------------------------------
|
| Task 7 (madde 3a): the audit sink was split in two — Spatie activity_log
| for model changes vs a Log::channel('stack') FILE for the domain listeners.
| A role's permission change (a pivot the HasActivityLogging trait cannot
| observe) therefore never reached the admin ActivityLog screen.
|
| Fix: the domain Log* listeners now write to the Spatie `audit` log channel
| instead of the log file. The trait still owns attribute-level history on the
| Spatie default channel; the listeners own the role↔permission pivot on the
| `audit` channel. No single fact is logged twice.
|
| These tests drive the REAL UpdateRoleAction (dispatch → sync-queue listener →
| activity row), proving:
|   1. a permission-only update writes exactly ONE `audit` row with the new
|      permission set in its properties;
|   2. an attribute-only update writes ZERO `audit` rows (the trait handles it
|      on the default channel) — the no-double-log guarantee.
|
| The permission + activity_log tables are built inline here (mirroring the
| project's Schema-builder test convention); DatabaseTestCase only provisions
| the FileManager/settings tables.
|
*/

use App\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Domain\Role\Actions\UpdateRoleAction;
use Lvntr\StarterKit\Domain\Role\DTOs\RoleDTO;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! class_exists(Role::class)) {
    require_once dirname(__DIR__, 3).'/stubs/app/Models/Role.php';
}

beforeEach(function (): void {
    // ShouldQueue listener → run inline so the audit side effect is observable.
    config(['queue.default' => 'sync']);

    // spatie/laravel-permission is not auto-discovered by Testbench here, so its
    // config is absent. Load the package defaults, then point the role model at
    // the consumer stub. The PermissionRegistrar reads this config on resolve.
    config(['permission' => require dirname(__DIR__, 3).'/vendor/spatie/laravel-permission/config/permission.php']);
    config(['permission.models.role' => Role::class]);
    app()->forgetInstance(PermissionRegistrar::class);

    // activity('audit') target table.
    Schema::create('activity_log', function (Blueprint $table): void {
        $table->id();
        $table->string('log_name')->nullable()->index();
        $table->text('description');
        $table->nullableMorphs('subject', 'subject');
        $table->string('event')->nullable();
        $table->nullableMorphs('causer', 'causer');
        $table->json('attribute_changes')->nullable();
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    // Spatie permission tables (non-teams, bigint keys).
    Schema::create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->text('display_name')->nullable();
        $table->string('group')->nullable();
        $table->string('color')->nullable();
        $table->text('seeded_permissions')->nullable();
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->string('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->string('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    foreach (['posts.read', 'posts.write', 'posts.delete'] as $name) {
        Permission::create(['name' => $name, 'guard_name' => 'web']);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * Create a role with a starting permission set (no domain event dispatched —
 * so the audit channel starts empty and only the action under test writes).
 */
function makeRoleWithPermissions(array $permissions): Role
{
    $role = Role::create([
        'name' => 'editor',
        'guard_name' => 'web',
        'display_name' => ['en' => 'Editor'],
        'group' => null,
        'color' => null,
    ]);

    $role->syncPermissions($permissions);
    $role->refresh();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $role;
}

it('writes exactly one audit row with the new permission set on a permission-only update', function (): void {
    $role = makeRoleWithPermissions(['posts.read']);

    // Same attributes, changed permissions → only the pivot changes.
    $dto = new RoleDTO(
        name: 'editor',
        displayName: ['en' => 'Editor'],
        permissions: ['posts.read', 'posts.write'],
    );

    app(UpdateRoleAction::class)->execute($role, $dto);

    $audit = Activity::query()->where('log_name', 'audit')->get();

    expect($audit)->toHaveCount(1);

    $entry = $audit->first();

    expect($entry->event)->toBe('updated')
        ->and($entry->description)->toBe('Role permissions updated')
        ->and($entry->subject_id)->toBe($role->id)
        ->and($entry->getProperty('changed_fields'))->toContain('permissions')
        ->and($entry->getProperty('permissions'))->toBe(['posts.read', 'posts.write']);
});

it('writes NO audit row on an attribute-only update (trait owns attribute history)', function (): void {
    $role = makeRoleWithPermissions(['posts.read']);

    // Change the display name only; keep the same permission set.
    $dto = new RoleDTO(
        name: 'editor',
        displayName: ['en' => 'Content Editor'],
        permissions: ['posts.read'],
    );

    app(UpdateRoleAction::class)->execute($role, $dto);

    // The pivot did not change → the audit-channel listener stays silent.
    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(0);

    // The attribute change is still recorded exactly once — by the
    // HasActivityLogging trait (its own channel), not by this listener. Proves
    // the change is not lost and the audit channel is not a duplicate of it.
    expect(Activity::query()->where('event', 'updated')->count())->toBe(1);
});

it('does not double-log when both attributes and permissions change', function (): void {
    $role = makeRoleWithPermissions(['posts.read']);

    $dto = new RoleDTO(
        name: 'editor',
        displayName: ['en' => 'Content Editor'],
        permissions: ['posts.read', 'posts.delete'],
    );

    app(UpdateRoleAction::class)->execute($role, $dto);

    // Exactly one audit row for the permission pivot...
    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(1);

    // ...and exactly two `updated` rows in total: one from the trait (attribute
    // change) and one from this listener (permission pivot). Each fact recorded
    // once, on its own channel — no double-log of either fact.
    expect(Activity::query()->where('event', 'updated')->count())->toBe(2);
    expect(Activity::query()->where('event', 'updated')->where('log_name', 'audit')->count())->toBe(1);
});

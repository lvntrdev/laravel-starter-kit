<?php

namespace Lvntr\StarterKit\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionServiceProvider;

/**
 * DB-aware base case for CheckResourcePermission middleware tests.
 *
 * Extends DatabaseTestCase (shared SQLite in-memory + users table) and layers
 * on the Spatie permission stack that the middleware depends on:
 *
 *   - PermissionServiceProvider is registered so that its Gate::before hook
 *     (which routes $user->can('foo') through checkPermissionTo) is active and
 *     the permission.php config is merged.
 *   - The five Spatie permission tables are created inline (no team columns),
 *     mirroring the vendor create_permission_tables migration. Inline schema is
 *     used instead of loadMigrationsFrom to stay consistent with the rest of
 *     the suite (see DatabaseTestCase docblock) and avoid the disabled Testbench
 *     fixture migrations.
 *
 * The tables are dropped-then-created (idempotent) so repeated migrateDatabases
 * calls never hit "table already exists".
 */
abstract class PermissionMiddlewareTestCase extends DatabaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            PermissionServiceProvider::class,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // No team scoping — keep the pivot schema single-column.
        $app['config']->set('permission.teams', false);
        $app['config']->set('permission.testing', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->createPermissionTables();
    }

    private function createPermissionTables(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

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
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_index');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
        });
    }
}

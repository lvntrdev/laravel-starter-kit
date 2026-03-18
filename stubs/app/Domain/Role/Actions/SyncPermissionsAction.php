<?php

namespace App\Domain\Role\Actions;

use App\Domain\Shared\Actions\BaseAction;
use Database\Seeders\_01_RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Action: Run the RolePermissionSeeder to sync permissions from config.
 * Ensures new permissions/roles from config are applied without a full re-seed.
 */
class SyncPermissionsAction extends BaseAction
{
    /**
     * Execute the action.
     */
    public function execute(): void
    {
        Artisan::call('db:seed', [
            '--class' => _01_RolePermissionSeeder::class,
            '--no-interaction' => true,
        ]);
    }
}

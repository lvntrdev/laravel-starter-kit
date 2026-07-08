<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;

use function Laravel\Prompts\spin;

class SeedPermissionsCommand extends Command
{
    protected $signature = 'sk:seed-permissions
        {--fresh : Reset all role permissions to match config exactly}';

    protected $description = 'Seed roles and permissions from config';

    public function handle(): int
    {
        $seederClass = 'Database\\Seeders\\_01_RolePermissionSeeder';

        if (! class_exists($seederClass)) {
            $this->components->error("Seeder class not found: {$seederClass}. Run sk:install first.");

            return self::FAILURE;
        }

        $seeder = new $seederClass;
        $seeder->setCommand($this);

        if ($this->option('fresh')) {
            $this->components->warn('Fresh mode: all roles will be synced to match config exactly.');
            $seeder->fresh = true;
        }

        spin(fn () => $seeder->run(), 'Seeding permissions...');

        // Invalidate the CheckResourcePermission name cache so freshly seeded
        // permissions are honored immediately — critical under Octane where the
        // worker (and its cache) outlives the seeding command.
        CheckResourcePermission::flushCache();

        $this->newLine();
        $this->components->info('Permissions seeded successfully.');

        return self::SUCCESS;
    }
}

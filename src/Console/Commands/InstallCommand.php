<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Hash;
use Lvntr\StarterKit\StarterKitServiceProvider;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'sk:install
        {--force : Overwrite existing files}
        {--no-interaction : Skip all prompts and use defaults}';

    protected $description = 'Install the Lvntr Starter Kit scaffolding';

    private Filesystem $files;

    /** @var list<string> */
    private array $published = [];

    /** @var list<string> */
    private array $skipped = [];

    public function handle(): int
    {
        $this->files = new Filesystem;

        $this->newLine();
        $this->components->info('Installing Lvntr Starter Kit...');
        $this->newLine();

        // 1. Publish stubs
        $this->publishStubs();

        // 2. Publish config
        $this->publishConfig();

        // 3. Create hash registry directory
        $this->createHashRegistry();

        // 4. Run migrations
        if ($this->confirmStep('Run database migrations?')) {
            spin(function () {
                return $this->callSilently('migrate', ['--force' => true]) === 0;
            }, 'Running migrations...');
            $this->components->info('Migrations completed.');
        }

        // 5. Run seeders
        if ($this->confirmStep('Run database seeders?')) {
            $this->runSeeders();
            $this->components->info('Seeders completed.');
        }

        // 6. Passport keys
        if ($this->confirmStep('Generate Passport encryption keys?')) {
            spin(function () {
                return $this->callSilently('passport:keys', ['--force' => true]) === 0;
            }, 'Installing Passport keys...');
            $this->components->info('Passport keys generated.');
        }

        // 7. Create admin user
        if ($this->confirmStep('Create default admin user?')) {
            $this->createAdminUser();
        }

        // 8. Install npm dependencies
        if ($this->confirmStep('Install npm dependencies and build assets?')) {
            $this->installFrontend();
        }

        // 9. Save stub hashes for update tracking
        $this->saveStubHashes();

        // Summary
        $this->newLine();
        $this->components->info('Lvntr Starter Kit installed successfully!');
        $this->newLine();

        if (! empty($this->published)) {
            $this->components->twoColumnDetail('<fg=green>Published</>', count($this->published).' files');
        }
        if (! empty($this->skipped)) {
            $this->components->twoColumnDetail('<fg=yellow>Skipped</>', count($this->skipped).' files (already exist, use --force to overwrite)');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Publish all stub files to the application.
     */
    private function publishStubs(): void
    {
        $stubsPath = StarterKitServiceProvider::stubsPath();
        $force = $this->option('force');

        $this->components->task('Publishing application scaffolding', function () use ($stubsPath, $force) {
            $this->publishDirectory($stubsPath, base_path(), $force);
        });
    }

    /**
     * Recursively publish a directory.
     */
    private function publishDirectory(string $source, string $destination, bool $force): void
    {
        if (! $this->files->isDirectory($source)) {
            return;
        }

        if (! $this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0755, true);
        }

        foreach ($this->files->allFiles($source, true) as $file) {
            $relativePath = $file->getRelativePathname();
            $targetPath = $destination.DIRECTORY_SEPARATOR.$relativePath;
            $targetDir = dirname($targetPath);

            if (! $this->files->isDirectory($targetDir)) {
                $this->files->makeDirectory($targetDir, 0755, true);
            }

            if (! $force && $this->files->exists($targetPath)) {
                $this->skipped[] = $relativePath;

                continue;
            }

            $this->files->copy($file->getPathname(), $targetPath);
            $this->published[] = $relativePath;
        }
    }

    /**
     * Publish package config file.
     */
    private function publishConfig(): void
    {
        $this->components->task('Publishing configuration', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'starter-kit-config',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Create the hash registry storage directory.
     */
    private function createHashRegistry(): void
    {
        $dir = storage_path('starter-kit');
        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }
    }

    /**
     * Discover and run seeders from the seeders directory.
     */
    private function runSeeders(): void
    {
        $seederPath = database_path('seeders');
        $files = glob($seederPath.'/_*.php');
        sort($files);

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $displayName = preg_replace('/^_\d+_/', '', $className);
            $fqcn = 'Database\\Seeders\\'.$className;

            if (! class_exists($fqcn)) {
                $this->components->warn("Class [{$fqcn}] not found — skipping.");

                continue;
            }

            spin(function () use ($fqcn) {
                return $this->callSilently('db:seed', [
                    '--class' => $fqcn,
                    '--force' => true,
                ]) === 0;
            }, "Seeding: {$displayName}...");
        }
    }

    /**
     * Create the default admin user.
     */
    private function createAdminUser(): void
    {
        $email = 'admin@demo.com';
        $password = 'password';

        if (! $this->option('no-interaction')) {
            $email = text('Admin email:', default: $email, required: true);
            $password = text('Admin password:', default: $password, required: true);
        }

        spin(function () use ($email, $password) {
            $userModel = config('auth.providers.users.model', \App\Models\User::class);

            if (! class_exists($userModel)) {
                return false;
            }

            $user = $userModel::create([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 1, // Active
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('system-admin');
            }

            return true;
        }, "Creating admin user ({$email})...");

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Admin Email</>', $email);
        $this->components->twoColumnDetail('<fg=green>Admin Password</>', $password);
    }

    /**
     * Install frontend dependencies and build.
     */
    private function installFrontend(): void
    {
        $this->components->task('Installing npm dependencies', function () {
            return exec('npm install 2>&1', $output, $code) !== false && $code === 0;
        });

        $this->components->task('Building frontend assets', function () {
            return exec('npm run build 2>&1', $output, $code) !== false && $code === 0;
        });
    }

    /**
     * Save hashes of published stub files for update tracking.
     */
    private function saveStubHashes(): void
    {
        $hashFile = config('starter-kit.published_hashes', storage_path('starter-kit/hashes.json'));
        $hashes = [];

        $stubsPath = StarterKitServiceProvider::stubsPath();

        foreach ($this->files->allFiles($stubsPath, true) as $file) {
            $relativePath = $file->getRelativePathname();
            $targetPath = base_path($relativePath);

            if ($this->files->exists($targetPath)) {
                $hashes[$relativePath] = md5_file($targetPath);
            }
        }

        $dir = dirname($hashFile);
        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        $this->files->put($hashFile, json_encode($hashes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Confirm a step, auto-accepting in no-interaction mode.
     */
    private function confirmStep(string $question): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        return confirm($question, default: true);
    }
}

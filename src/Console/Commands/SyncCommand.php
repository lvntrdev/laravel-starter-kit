<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\StarterKitServiceProvider;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class SyncCommand extends Command
{
    protected $signature = 'sk:sync
        {--all : Sync all directories without prompting}
        {--dry-run : Show what would be synced without actually copying}';

    protected $description = 'Sync project files back to Starter Kit package stubs';

    private Filesystem $files;

    private string $stubsPath;

    /**
     * Directories to sync from project → package stubs.
     *
     * @var array<string, string>
     */
    private const STUB_DIRS = [
        'app' => 'Application code (Controllers, Models, Domain, Providers, etc.)',
        'routes' => 'Route definitions',
        'database' => 'Migrations, seeders, factories',
        'resources/js/pages' => 'Vue page components',
        'resources/js/composables' => 'Vue composables',
        'resources/js/layouts' => 'Vue layouts',
        'resources/js/types' => 'TypeScript types',
        'resources/css' => 'Stylesheets',
        'lang' => 'Translation files',
        'config' => 'Config files (only starter-kit specific)',
        'bootstrap' => 'Bootstrap files',
    ];

    /**
     * Single files to sync from project → package stubs.
     *
     * @var list<string>
     */
    private const STUB_FILES = [
        'vite.config.ts',
        'tsconfig.json',
        'package.json',
        'resources/js/app.ts',
        'resources/js/ssr.ts',
    ];

    /**
     * Directories to sync from project → package source (not stubs).
     * These are package-owned components that live in vendor.
     *
     * @var array<string, string>
     */
    private const PACKAGE_DIRS = [
        'resources/js/components/Lvntr-Starter-Kit' => 'resources/js/components',
    ];

    public function handle(): int
    {
        $this->files = new Filesystem;
        $this->stubsPath = StarterKitServiceProvider::stubsPath();

        $this->newLine();
        $this->components->info('Sync Project → Package');
        $this->newLine();

        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->components->warn('DRY RUN — no files will be copied.');
            $this->newLine();
        }

        // 1. Select what to sync
        $selectedDirs = $this->selectDirectories();

        if (empty($selectedDirs)) {
            $this->components->warn('Nothing selected.');

            return self::SUCCESS;
        }

        $totalCopied = 0;
        $totalSkipped = 0;

        // 2. Sync selected stub directories
        foreach ($selectedDirs as $dir) {
            if (! isset(self::STUB_DIRS[$dir])) {
                continue;
            }

            $source = base_path($dir);
            $destination = $this->stubsPath.DIRECTORY_SEPARATOR.$dir;

            if (! $this->files->isDirectory($source)) {
                $this->components->warn("Source not found: {$dir}/");

                continue;
            }

            $result = $this->syncDirectory($source, $destination, $dir, $isDryRun);
            $totalCopied += $result['copied'];
            $totalSkipped += $result['skipped'];
        }

        // 3. Sync stub files
        foreach (self::STUB_FILES as $file) {
            $source = base_path($file);
            $destination = $this->stubsPath.DIRECTORY_SEPARATOR.$file;

            if (! $this->files->exists($source)) {
                continue;
            }

            if ($this->files->exists($destination) && md5_file($source) === md5_file($destination)) {
                $totalSkipped++;

                continue;
            }

            if ($isDryRun) {
                $this->line("  <fg=cyan>WOULD COPY</> {$file}");
                $totalCopied++;
            } else {
                $dir = dirname($destination);
                if (! $this->files->isDirectory($dir)) {
                    $this->files->makeDirectory($dir, 0755, true);
                }
                $this->files->copy($source, $destination);
                $totalCopied++;
            }
        }

        // 4. Sync package-owned component directories (project → package src)
        foreach (self::PACKAGE_DIRS as $projectDir => $packageDir) {
            $source = base_path($projectDir);
            $destination = StarterKitServiceProvider::basePath($packageDir);

            if (! $this->files->isDirectory($source)) {
                continue;
            }

            $result = $this->syncDirectory($source, $destination, "package:{$packageDir}", $isDryRun);
            $totalCopied += $result['copied'];
            $totalSkipped += $result['skipped'];
        }

        // 5. Summary
        $this->newLine();
        $verb = $isDryRun ? 'Would sync' : 'Synced';
        $this->components->twoColumnDetail("<fg=green>{$verb}</>", "{$totalCopied} files updated, {$totalSkipped} unchanged");

        if (! $isDryRun && $totalCopied > 0) {
            $this->newLine();
            $this->line('  Next steps:');
            $this->line('  <fg=cyan>php artisan sk:release</> — tag and push to Git');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Let user pick which directories to sync.
     *
     * @return list<string>
     */
    private function selectDirectories(): array
    {
        if ($this->option('all')) {
            return array_keys(self::STUB_DIRS);
        }

        return multiselect(
            label: 'Which directories to sync?',
            options: self::STUB_DIRS,
            default: array_keys(self::STUB_DIRS),
        );
    }

    /**
     * Sync a directory, copying only changed files.
     *
     * @return array{copied: int, skipped: int}
     */
    private function syncDirectory(string $source, string $destination, string $label, bool $isDryRun): array
    {
        $copied = 0;
        $skipped = 0;

        if (! $this->files->isDirectory($destination)) {
            if (! $isDryRun) {
                $this->files->makeDirectory($destination, 0755, true);
            }
        }

        foreach ($this->files->allFiles($source, true) as $file) {
            $relativePath = $file->getRelativePathname();
            $targetPath = $destination.DIRECTORY_SEPARATOR.$relativePath;

            // Skip if identical
            if ($this->files->exists($targetPath) && md5_file($file->getPathname()) === md5_file($targetPath)) {
                $skipped++;

                continue;
            }

            if ($isDryRun) {
                $status = $this->files->exists($targetPath) ? 'CHANGED' : 'NEW';
                $this->line("  <fg=cyan>{$status}</> {$label}/{$relativePath}");
            } else {
                $targetDir = dirname($targetPath);
                if (! $this->files->isDirectory($targetDir)) {
                    $this->files->makeDirectory($targetDir, 0755, true);
                }
                $this->files->copy($file->getPathname(), $targetPath);
            }

            $copied++;
        }

        if ($copied > 0 && ! $isDryRun) {
            $this->components->twoColumnDetail($label, "<fg=green>{$copied} files</>");
        } elseif ($copied === 0) {
            $this->components->twoColumnDetail($label, '<fg=gray>unchanged</>');
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }
}

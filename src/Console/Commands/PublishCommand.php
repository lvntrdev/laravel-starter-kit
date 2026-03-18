<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\StarterKitServiceProvider;

use function Laravel\Prompts\select;

class PublishCommand extends Command
{
    protected $signature = 'sk:publish
        {--tag= : Specific tag to publish (components, lang, config)}
        {--force : Overwrite existing files}';

    protected $description = 'Publish optional Starter Kit assets for customization';

    /** @var array<string, array{source: string, destination: string, label: string}> */
    private const PUBLISHABLE_TAGS = [
        'components' => [
            'source' => 'resources/js/components',
            'destination' => 'resources/js/components/Lvntr-Starter-Kit',
            'label' => 'Vue Components (FormBuilder, DatatableBuilder, TabBuilder, Skeleton, UI)',
        ],
        'lang' => [
            'source' => 'resources/lang',
            'destination' => 'lang/vendor/starter-kit',
            'label' => 'Language Files (translations)',
        ],
        'config' => [
            'source' => 'config/starter-kit.php',
            'destination' => 'config/starter-kit.php',
            'label' => 'Configuration File',
        ],
    ];

    public function handle(): int
    {
        $files = new Filesystem;
        $tag = $this->option('tag');
        $force = (bool) $this->option('force');

        if (! $tag) {
            $tag = select(
                label: 'What would you like to publish?',
                options: collect(self::PUBLISHABLE_TAGS)->mapWithKeys(
                    fn (array $config, string $key) => [$key => $config['label']]
                )->all(),
            );
        }

        if (! isset(self::PUBLISHABLE_TAGS[$tag])) {
            $this->components->error("Unknown tag: {$tag}");
            $this->line('Available tags: '.implode(', ', array_keys(self::PUBLISHABLE_TAGS)));

            return self::FAILURE;
        }

        $config = self::PUBLISHABLE_TAGS[$tag];
        $source = StarterKitServiceProvider::basePath($config['source']);
        $destination = base_path($config['destination']);

        if (! $files->exists($source)) {
            $this->components->error("Source not found: {$source}");

            return self::FAILURE;
        }

        $count = 0;

        $this->components->task("Publishing {$config['label']}", function () use ($files, $source, $destination, $force, &$count) {
            if ($files->isDirectory($source)) {
                $count = $this->publishDirectory($files, $source, $destination, $force);
            } else {
                if ($force || ! $files->exists($destination)) {
                    $dir = dirname($destination);
                    if (! $files->isDirectory($dir)) {
                        $files->makeDirectory($dir, 0755, true);
                    }
                    $files->copy($source, $destination);
                    $count = 1;
                }
            }
        });

        $this->newLine();

        if ($count > 0) {
            $this->components->info("Published {$count} file(s) to: {$config['destination']}");
        } else {
            $this->components->warn('No files published. Files already exist (use --force to overwrite).');
        }

        return self::SUCCESS;
    }

    /**
     * Recursively publish a directory.
     */
    private function publishDirectory(Filesystem $files, string $source, string $destination, bool $force): int
    {
        $count = 0;

        if (! $files->isDirectory($destination)) {
            $files->makeDirectory($destination, 0755, true);
        }

        foreach ($files->allFiles($source, true) as $file) {
            $targetPath = $destination.DIRECTORY_SEPARATOR.$file->getRelativePathname();
            $targetDir = dirname($targetPath);

            if (! $files->isDirectory($targetDir)) {
                $files->makeDirectory($targetDir, 0755, true);
            }

            if (! $force && $files->exists($targetPath)) {
                continue;
            }

            $files->copy($file->getPathname(), $targetPath);
            $count++;
        }

        return $count;
    }
}

<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ReleaseCommand extends Command
{
    protected $signature = 'sk:release';

    protected $description = 'Tag and push a new Starter Kit version to Git';

    private string $packagePath;

    public function handle(): int
    {
        $this->packagePath = StarterKitServiceProvider::basePath();

        $this->newLine();
        $this->components->info('Starter Kit Release');
        $this->newLine();

        // 1. Check remote is configured
        if (! $this->hasRemote()) {
            $this->components->error('No git remote configured for the package.');
            $this->line('  Run: cd '.escapeshellarg($this->packagePath));
            $this->line('  Then: git remote add origin <your-repo-url>');

            return self::FAILURE;
        }

        // 2. Check working tree is clean
        if (! $this->isClean()) {
            $this->components->warn('Package has uncommitted changes:');
            $this->newLine();
            $this->line($this->git('status', '--short'));
            $this->newLine();

            if (! confirm('Commit all changes before release?', true)) {
                $this->components->error('Cannot release with uncommitted changes.');

                return self::FAILURE;
            }

            $message = text('Commit message:', default: 'chore: prepare release', required: true);

            $this->git('add', '-A');
            $this->git('commit', '-m', $message);
            $this->components->twoColumnDetail('Changes committed', '<fg=green>DONE</>');
        }

        // 3. Show current version
        $currentTag = $this->getLatestTag();
        if ($currentTag) {
            $this->components->twoColumnDetail('Current version', "<fg=cyan>{$currentTag}</>");
        } else {
            $this->components->twoColumnDetail('Current version', '<fg=yellow>no tags yet</>');
        }

        // 4. Ask for new version
        $suggestedVersion = $this->suggestNextVersion($currentTag);

        $bumpType = select(
            label: 'Version bump type',
            options: [
                'patch' => "Patch (bug fixes) → {$this->bumpVersion($currentTag, 'patch')}",
                'minor' => "Minor (new features) → {$this->bumpVersion($currentTag, 'minor')}",
                'major' => "Major (breaking changes) → {$this->bumpVersion($currentTag, 'major')}",
                'custom' => 'Custom version',
            ],
            default: 'patch',
        );

        if ($bumpType === 'custom') {
            $version = text('Version (e.g. 1.0.0):', required: true);
        } else {
            $version = $this->bumpVersion($currentTag, $bumpType);
        }

        // Ensure v prefix
        if (! str_starts_with($version, 'v')) {
            $version = 'v'.$version;
        }

        // 5. Check tag doesn't already exist
        $existingTags = $this->git('tag', '-l', $version);
        if (trim($existingTags) === $version) {
            $this->components->error("Tag {$version} already exists.");

            return self::FAILURE;
        }

        // 6. Confirm
        $this->newLine();
        $this->components->twoColumnDetail('New version', "<fg=green>{$version}</>");
        $remote = trim($this->git('remote', 'get-url', 'origin'));
        $this->components->twoColumnDetail('Remote', $remote);
        $this->newLine();

        if (! confirm("Release {$version}?", true)) {
            $this->components->warn('Release cancelled.');

            return self::SUCCESS;
        }

        // 7. Create tag
        $this->git('tag', '-a', $version, '-m', "Release {$version}");
        $this->components->twoColumnDetail("Tag {$version}", '<fg=green>CREATED</>');

        // 8. Push commits + tags
        $this->line('  <fg=gray>→</> Pushing to remote...');
        $pushResult = $this->git('push', 'origin', 'main', '--tags');
        $this->components->twoColumnDetail('Push', '<fg=green>DONE</>');

        // 9. Summary
        $this->newLine();
        $this->components->info("Released {$version} successfully!");
        $this->newLine();
        $this->line('  Install with:');
        $this->line("  <fg=cyan>composer require lvntr/starter-kit:\"^{$this->stripV($version)}\"</>");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Run a git command in the package directory.
     */
    private function git(string ...$args): string
    {
        $process = new Process(['git', ...$args], $this->packagePath);
        $process->run();

        return $process->getOutput();
    }

    private function hasRemote(): bool
    {
        return trim($this->git('remote')) !== '';
    }

    private function isClean(): bool
    {
        return trim($this->git('status', '--porcelain')) === '';
    }

    private function getLatestTag(): ?string
    {
        $tag = trim($this->git('describe', '--tags', '--abbrev=0'));

        return $tag !== '' ? $tag : null;
    }

    /**
     * @return array{int, int, int}
     */
    private function parseVersion(?string $tag): array
    {
        if (! $tag) {
            return [0, 0, 0];
        }

        $clean = ltrim($tag, 'v');
        $parts = explode('.', $clean);

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    private function bumpVersion(?string $currentTag, string $type): string
    {
        [$major, $minor, $patch] = $this->parseVersion($currentTag);

        return match ($type) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            'patch' => $major.'.'.$minor.'.'.($patch + 1),
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }

    private function suggestNextVersion(?string $currentTag): string
    {
        return $this->bumpVersion($currentTag, 'patch');
    }

    private function stripV(string $version): string
    {
        return ltrim($version, 'v');
    }
}

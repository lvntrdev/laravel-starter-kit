<?php

namespace Lvntr\StarterKit\Console\Commands\Concerns;

use Lvntr\StarterKit\StarterKitServiceProvider;

/**
 * Mirror kit-owned Claude skills into Codex's project-level skills directory.
 *
 * The mirror never prunes: a kit skill dir under .codex/skills/ is only
 * replaced when its .claude/skills/ source exists; everything else under
 * .codex/skills/ (consumer-authored skills, orphaned targets) is left alone.
 *
 * Requires the using command to expose an Illuminate\Filesystem\Filesystem
 * instance as $this->files and Laravel's console component factory as
 * $this->components.
 */
trait MirrorsAiSkills
{
    /**
     * Replace each kit-owned Codex skill with the consumer's published Claude copy.
     */
    protected function mirrorAiSkills(bool $dryRun = false, bool $skipped = false): int
    {
        $stubSkillsPath = StarterKitServiceProvider::stubsPath('.claude/skills');
        $sourceSkillsPath = base_path('.claude/skills');

        if ($skipped
            || ! $this->files->isDirectory($stubSkillsPath)
            || ! $this->files->isDirectory($sourceSkillsPath)) {
            return 0;
        }

        $fileCount = 0;

        foreach ($this->files->directories($stubSkillsPath) as $stubSkillPath) {
            $skillName = basename($stubSkillPath);
            $source = $sourceSkillsPath.DIRECTORY_SEPARATOR.$skillName;
            $target = base_path('.codex/skills/'.$skillName);

            // No published Claude copy → leave any existing target alone.
            if (! $this->files->isDirectory($source)) {
                continue;
            }

            $fileCount += count($this->files->allFiles($source, true));

            if ($dryRun) {
                continue;
            }

            if ($this->files->isDirectory($target)) {
                $this->files->deleteDirectory($target);
            } elseif ($this->files->exists($target)) {
                $this->files->delete($target);
            }

            $this->files->copyDirectory($source, $target);
        }

        if ($fileCount === 0) {
            return 0;
        }

        $message = $dryRun
            ? "AI skills would be mirrored to .codex/skills ({$fileCount} files)"
            : "AI skills mirrored to .codex/skills ({$fileCount} files)";

        $this->components->info($message);

        return $fileCount;
    }

    /**
     * Determine whether the install registry explicitly opted out of AI skills.
     *
     * @param  array<string, string>  $hashes
     */
    protected function aiSkillsWereSkipped(array $hashes): bool
    {
        foreach ($hashes as $path => $hash) {
            $normalizedPath = str_replace('\\', '/', $path);

            if ($hash === '__skipped__' && str_starts_with($normalizedPath, '.claude/skills/')) {
                return true;
            }
        }

        return false;
    }
}

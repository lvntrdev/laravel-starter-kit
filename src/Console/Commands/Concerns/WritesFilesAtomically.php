<?php

namespace Lvntr\StarterKit\Console\Commands\Concerns;

use RuntimeException;
use Throwable;

/**
 * Atomic file writes for install/update/eject commands.
 *
 * A plain Filesystem::put() truncates the target the instant it opens the
 * handle, so an interruption mid-write (Ctrl-C, crash, disk-full, killed
 * process) leaves a half-written or empty file. For the hash registry
 * (hashes.json) that means a corrupted registry — the most expensive failure,
 * because the next sk:update can no longer tell consumer-owned files from
 * removable ones.
 *
 * atomicPut() writes into a sibling temp file and then rename()s it over the
 * target. rename() is atomic on POSIX filesystems, so a reader/consumer either
 * sees the complete old file or the complete new file — never a truncated one.
 * The temp file is created in the SAME directory as the target on purpose: a
 * cross-filesystem rename degrades into copy+unlink and loses atomicity, so
 * keeping temp and target on one filesystem preserves the guarantee.
 *
 * Requires the using class to expose an Illuminate\Filesystem\Filesystem
 * instance as $this->files.
 */
trait WritesFilesAtomically
{
    /**
     * Write $contents to $path atomically (temp file + rename).
     */
    protected function atomicPut(string $path, string $contents): void
    {
        $dir = dirname($path);

        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        // Temp file lives next to the target so the final rename stays within a
        // single filesystem (see class docblock). The leading dot + random
        // suffix keep concurrent writes and directory scanners from colliding.
        $temp = $dir.DIRECTORY_SEPARATOR.'.'.basename($path).'.tmp'.bin2hex(random_bytes(6));

        try {
            $this->files->put($temp, $contents);

            if (! @rename($temp, $path)) {
                throw new RuntimeException("Atomic write failed: could not move temp file into place for [{$path}].");
            }
        } catch (Throwable $e) {
            if ($this->files->exists($temp)) {
                $this->files->delete($temp);
            }

            throw $e;
        }
    }
}

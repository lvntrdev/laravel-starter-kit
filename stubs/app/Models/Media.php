<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * Custom Media model: extends Spatie's default to add SoftDeletes support
 * for the FileManager trash feature.
 *
 * Spatie's MediaObserver already honours `isForceDeleting()`, so a regular
 * `delete()` call leaves the physical file on disk while marking the row as
 * trashed; only `forceDelete()` removes the file via Spatie's filesystem.
 *
 * Trash is scoped to the FileManager `files` collection ONLY. Every other
 * collection (avatar, logo, form attachments, editor uploads, ...) must be
 * permanently removed on delete: those records never appear in the trash UI,
 * the purge command does not cover them, and soft-deleted rows would keep
 * counting toward the storage quota while their files linger on disk. The
 * `delete()` override below converts such deletes into `forceDelete()` —
 * this also covers deletes issued internally by Spatie (e.g. single-file
 * collection replacement via `clearMediaCollection()`).
 */
class Media extends SpatieMedia
{
    use SoftDeletes;

    /**
     * The only collection whose deletes go through the trash (soft delete).
     * Mirrors the literal used across the FileManager domain queries/actions.
     */
    private const FILE_MANAGER_COLLECTION = 'files';

    protected static function booted(): void
    {
        // Cascade-clean favorites when media is permanently removed.
        // Soft deletes intentionally leave favorites in place so a restore
        // brings the favorited state back with the file.
        static::forceDeleted(function (Media $media): void {
            FileFavorite::query()
                ->where('favoritable_type', 'file')
                ->where('favoritable_id', (string) $media->id)
                ->delete();
        });
    }

    /**
     * Route deletes through the trash only for FileManager files (and only
     * while the trash feature is enabled); everything else is permanent.
     */
    public function delete(): ?bool
    {
        // forceDelete() re-enters delete() with the flag set — pass through,
        // otherwise we would recurse forever.
        if ($this->forceDeleting) {
            return parent::delete();
        }

        if (! $this->shouldMoveToTrash()) {
            return $this->forceDelete();
        }

        return parent::delete();
    }

    protected function shouldMoveToTrash(): bool
    {
        return $this->collection_name === self::FILE_MANAGER_COLLECTION
            && (bool) config('file-manager.settings.enable_trash', true);
    }
}

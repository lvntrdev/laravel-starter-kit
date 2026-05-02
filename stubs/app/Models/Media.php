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
 */
class Media extends SpatieMedia
{
    use SoftDeletes;

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
}

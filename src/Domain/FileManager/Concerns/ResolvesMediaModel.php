<?php

namespace Lvntr\StarterKit\Domain\FileManager\Concerns;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolves the concrete Media Eloquent model class from the
 * media-library config so consumers can extend or swap the model
 * (e.g. to add SoftDeletes) without forking vendor code.
 *
 * @phpstan-type MediaClass class-string<Media>
 */
trait ResolvesMediaModel
{
    /**
     * Return the configured media model class.
     *
     * Falls back to Spatie's base model when the config key is absent,
     * preserving behaviour on minimal / test installs.
     *
     * @return class-string<Media>
     */
    protected function mediaModel(): string
    {
        /** @var class-string<Media> $class */
        $class = config('media-library.media_model', Media::class);

        return $class;
    }

    /**
     * Total bytes consumed by ALL media in the system, across every owner /
     * collection / context, including soft-deleted (trash) entries —
     * represents true disk usage for quota accounting.
     */
    protected function computeStorageUsed(): int
    {
        $mediaModel = $this->mediaModel();

        /** @var object{s: int|string}|null $row */
        $row = $mediaModel::withTrashed()
            ->selectRaw('coalesce(sum(size), 0) as s')
            ->first();

        return $row ? (int) $row->s : 0;
    }

    /**
     * Maximum allowed storage in bytes, read from config.
     */
    protected function storageQuotaBytes(): int
    {
        return (int) config('file-manager.settings.storage_quota_gb', 10) * 1024 * 1024 * 1024;
    }
}

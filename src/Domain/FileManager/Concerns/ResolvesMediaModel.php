<?php

namespace Lvntr\StarterKit\Domain\FileManager\Concerns;

use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
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
     * Total bytes consumed by ALL media belonging to this context owner,
     * across every collection (file manager, avatars, form uploads, etc.).
     * Used for the storage-usage indicator in the sidebar.
     */
    protected function computeStorageUsed(FileManagerContextDTO $context): int
    {
        $mediaModel = $this->mediaModel();

        /** @var object{s: int|string}|null $row */
        $row = $mediaModel::query()
            ->where('model_type', $context->ownerType)
            ->where('model_id', $context->ownerId)
            ->selectRaw('coalesce(sum(size), 0) as s')
            ->first();

        return $row ? (int) $row->s : 0;
    }
}

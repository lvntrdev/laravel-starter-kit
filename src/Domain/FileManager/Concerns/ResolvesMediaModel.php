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
}

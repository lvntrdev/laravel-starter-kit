<?php

namespace Lvntr\StarterKit\Traits;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\InteractsWithMedia;

trait HasMediaCollections
{
    use InteractsWithMedia;

    /**
     * Sync uploaded files for a given media collection.
     *
     * Accepts an array of:
     *  - UploadedFile instances (new uploads)
     *  - Existing media IDs (integers/strings) to keep
     *
     * Any previously attached media not in $keepIds will be removed.
     *
     * @param  array<int, UploadedFile|int|string>  $items
     */
    public function syncMediaCollection(string $collection, array $items): void
    {
        $keepIds = [];
        $newFiles = [];

        foreach ($items as $item) {
            if ($item instanceof UploadedFile) {
                $newFiles[] = $item;
            } else {
                $keepIds[] = (int) $item;
            }
        }

        // Remove media that should no longer be kept
        $this->getMedia($collection)
            ->reject(fn ($media) => in_array($media->id, $keepIds, true))
            ->each(fn ($media) => $media->delete());

        // Add new uploads
        foreach ($newFiles as $file) {
            $this->addMedia($file)->toMediaCollection($collection);
        }
    }

    /**
     * Get serialized media for frontend consumption.
     *
     * @return array<int, array{id: int, name: string, url: string, size: int, mime_type: string}>
     */
    public function getMediaForForm(string $collection): array
    {
        return $this->getMedia($collection)->map(function ($media) {
            try {
                $url = $media->getTemporaryUrl(now()->addMinutes(30));
            } catch (\RuntimeException) {
                $url = $media->getUrl();
            }

            return [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $url,
                'size' => $media->size,
                'mime_type' => $media->mime_type,
            ];
        })->values()->all();
    }
}

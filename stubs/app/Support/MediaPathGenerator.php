<?php

namespace App\Support;

use App\Domain\FileManager\Support\ContextRegistry;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Custom path generator for media files.
 *
 * Default format: {model_type}/{model_id}/{collection_name}/
 *   e.g. user/{id}/avatar/ or user/{id}/form/identify/
 *
 * FileManager "files" collection reads its base path from the ContextRegistry
 * so that every registered context (user, global, vehicle, school, …) gets a
 * stable, folder-agnostic layout:
 *   - template `user/{id}/files`    →  user/{userId}/files/{mediaUuid}/
 *   - template `global/files`       →  global/files/{mediaUuid}/
 *   - template `vehicle/{id}/files` →  vehicle/{vehicleId}/files/{mediaUuid}/
 */
class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    private function getBasePath(Media $media): string
    {
        if ($media->collection_name === 'files') {
            return $this->getFileManagerBasePath($media);
        }

        $modelType = strtolower(class_basename($media->model_type));
        $modelId = $media->model_id;
        $collection = str_replace('.', '/', $media->collection_name);

        return "{$modelType}/{$modelId}/{$collection}";
    }

    private function getFileManagerBasePath(Media $media): string
    {
        $mediaId = $media->uuid ?? $media->id;

        /** @var ContextRegistry $registry */
        $registry = app(ContextRegistry::class);
        $contextKey = $registry->keyForModel((string) $media->model_type);

        if ($contextKey !== null) {
            $base = $registry->pathFor($contextKey, (string) $media->model_id);

            return "{$base}/{$mediaId}";
        }

        // Fallback for media created before the registry existed.
        $modelType = strtolower(class_basename((string) $media->model_type));

        return "{$modelType}/{$media->model_id}/files/{$mediaId}";
    }
}

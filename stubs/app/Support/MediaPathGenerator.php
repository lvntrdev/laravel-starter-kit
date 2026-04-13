<?php

namespace App\Support;

use App\Models\GlobalFileBucket;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Custom path generator for media files.
 *
 * Default format: {model_type}/{model_id}/{collection_name}/
 *   e.g. user/{id}/avatar/ or user/{id}/form/identify/
 *
 * FileManager "files" collection uses a flat, folder-agnostic layout so that
 * logical folder moves stay DB-only (no physical file moves):
 *   - User context:   user/{userId}/files/{mediaUuid}/
 *   - Global context: global/files/{mediaUuid}/
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

        if ($media->model_type === GlobalFileBucket::class) {
            return "global/files/{$mediaId}";
        }

        $modelType = strtolower(class_basename($media->model_type));

        return "{$modelType}/{$media->model_id}/files/{$mediaId}";
    }
}

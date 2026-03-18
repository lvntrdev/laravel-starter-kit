<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Custom path generator for media files.
 *
 * Generates paths like: user/{id}/avatar/ or user/{id}/form/identify/
 * Format: {model_type}/{model_id}/{collection_name}/
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

    /**
     * Build the base path: {model_type}/{model_id}/{collection_name}
     */
    private function getBasePath(Media $media): string
    {
        $modelType = strtolower(class_basename($media->model_type));
        $modelId = $media->model_id;
        $collection = str_replace('.', '/', $media->collection_name);

        return "{$modelType}/{$modelId}/{$collection}";
    }
}

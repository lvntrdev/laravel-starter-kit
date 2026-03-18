<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaUploadController extends Controller
{
    /**
     * Delete a media item by ID.
     */
    public function destroy(Media $media): ApiResponse|JsonResponse
    {
        if (! $media->model) {
            throw ApiException::forbidden('Media item cannot be deleted.');
        }

        Gate::authorize('delete', $media->model);

        $media->delete();

        return to_api(status: 204);
    }
}

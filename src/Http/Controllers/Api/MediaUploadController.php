<?php

namespace Lvntr\StarterKit\Http\Controllers\Api;

use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Lvntr\StarterKit\Exceptions\ApiException;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

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

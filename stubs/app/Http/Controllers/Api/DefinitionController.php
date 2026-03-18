<?php

namespace App\Http\Controllers\Api;

use App\Domain\Shared\Services\DefinitionService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class DefinitionController extends Controller
{
    /**
     * Get all definitions (enum + DB), optionally filtered by keys.
     *
     * GET /api/v1/definitions
     * GET /api/v1/definitions?keys=userStatus,identityType
     */
    public function index(Request $request, DefinitionService $service): ApiResponse
    {
        $keys = $request->has('keys')
            ? array_filter(explode(',', $request->input('keys')))
            : null;

        return to_api($service->all($keys));
    }
}

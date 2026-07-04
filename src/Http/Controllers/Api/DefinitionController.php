<?php

namespace Lvntr\StarterKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Http\Controllers\Concerns\ListsDefinitions;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

class DefinitionController extends Controller
{
    use ListsDefinitions;

    /**
     * Get all definitions (enum + DB), optionally filtered by keys.
     *
     * GET /api/v1/definitions
     * GET /api/v1/definitions?keys=userStatus,identityType
     */
    public function index(Request $request, DefinitionService $service): ApiResponse
    {
        return $this->listDefinitions($request, $service);
    }
}

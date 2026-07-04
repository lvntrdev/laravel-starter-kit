<?php

namespace Lvntr\StarterKit\Http\Controllers\Service;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Http\Controllers\Concerns\ListsDefinitions;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

class DefinitionServiceController extends Controller
{
    use ListsDefinitions;

    /**
     * Get definitions filtered by keys, for use in forms.
     *
     * GET /definitions?keys=gender,system
     */
    public function index(Request $request, DefinitionService $service): ApiResponse
    {
        return $this->listDefinitions($request, $service);
    }
}

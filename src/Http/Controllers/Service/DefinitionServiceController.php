<?php

namespace Lvntr\StarterKit\Http\Controllers\Service;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

class DefinitionServiceController extends Controller
{
    /**
     * Get definitions filtered by keys, for use in forms.
     *
     * GET /definitions?keys=gender,system
     */
    public function index(Request $request, DefinitionService $service): ApiResponse
    {
        $keys = $request->has('keys')
            ? array_filter(explode(',', $request->input('keys')))
            : null;

        return to_api($service->all($keys));
    }
}

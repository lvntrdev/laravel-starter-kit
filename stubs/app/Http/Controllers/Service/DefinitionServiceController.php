<?php

namespace App\Http\Controllers\Service;

use App\Domain\Shared\Services\DefinitionService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

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

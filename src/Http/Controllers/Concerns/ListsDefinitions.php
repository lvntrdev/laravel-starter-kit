<?php

namespace Lvntr\StarterKit\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

/**
 * Shared body for the definitions listing endpoints.
 *
 * `Api\DefinitionController` and `Service\DefinitionServiceController` expose
 * the same request/response shape under two different route prefixes
 * (`/api/v1/definitions` vs `/definitions`); this trait keeps the parsing +
 * response logic in one place while leaving each controller's own class
 * (and therefore its route binding) untouched.
 */
trait ListsDefinitions
{
    /**
     * Get all definitions (enum + DB), optionally filtered by keys.
     */
    protected function listDefinitions(Request $request, DefinitionService $service): ApiResponse
    {
        $keys = $request->has('keys')
            ? array_filter(explode(',', $request->input('keys')))
            : null;

        return to_api($service->all($keys));
    }
}

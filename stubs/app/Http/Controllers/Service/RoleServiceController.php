<?php

namespace App\Http\Controllers\Service;

use App\Domain\Role\Queries\RoleSelectOptionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;

/**
 * Service controller for role-related API endpoints.
 *
 * Provides reusable endpoints that can be consumed from any Vue component
 * via Wayfinder-generated TypeScript actions.
 */
class RoleServiceController extends Controller
{
    /**
     * Return available roles as select options.
     *
     * @return ApiResponse data: array<{label: string, value: string}>
     */
    public function getRoles(RoleSelectOptionsQuery $query): ApiResponse
    {
        return to_api($query->get(auth()->user()));
    }
}

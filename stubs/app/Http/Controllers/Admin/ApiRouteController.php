<?php

namespace App\Http\Controllers\Admin;

use App\Domain\ApiRoute\Actions\RegenerateApiDocsAction;
use App\Domain\ApiRoute\Queries\ApiRouteListQuery;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApiRouteController extends Controller
{
    public function index(ApiRouteListQuery $query): Response
    {
        return Inertia::render('Admin/ApiRoutes/Index', [
            'routes' => $query->get(),
        ]);
    }

    public function regenerateDocs(RegenerateApiDocsAction $action): ApiResponse
    {
        $output = $action->execute();

        return to_api(['output' => $output], 'API documentation regenerated successfully.');
    }
}

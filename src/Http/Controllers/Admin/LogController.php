<?php

namespace Lvntr\StarterKit\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lvntr\StarterKit\Domain\Logs\Actions\DeleteLogFilesAction;
use Lvntr\StarterKit\Domain\Logs\DTOs\DeleteLogFilesDTO;
use Lvntr\StarterKit\Domain\Logs\DTOs\LogEntryFilterDTO;
use Lvntr\StarterKit\Domain\Logs\Queries\LogEntryQuery;
use Lvntr\StarterKit\Domain\Logs\Queries\LogFileQuery;
use Lvntr\StarterKit\Http\Requests\Admin\Log\DeleteLogFilesRequest;
use Lvntr\StarterKit\Http\Requests\Admin\Log\EntryFilterRequest;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

/**
 * System-admin-only log file viewer.
 *
 * Thin per project convention:
 *   - Auth gate → role:system_admin route middleware
 *   - Validation → FormRequest
 *   - Listing / streaming → Query
 *   - Delete → Action
 */
class LogController extends Controller
{
    /**
     * Render the log files index page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Logs/Index');
    }

    /**
     * JSON datatable feed for the index page.
     */
    public function dtApi(LogFileQuery $query): ApiResponse
    {
        return $query->response();
    }

    /**
     * Render the log file viewer page with the file's metadata.
     * Initial entries are loaded by the page itself via the entries() endpoint.
     */
    public function show(string $filename, LogFileQuery $query): Response
    {
        $file = $query->find($filename);

        return Inertia::render('Admin/Logs/Show', [
            'file' => $file->toArray(),
        ]);
    }

    /**
     * JSON entry stream for a single file. Filters + pagination via cursor.
     */
    public function entries(string $filename, EntryFilterRequest $request, LogEntryQuery $query): ApiResponse
    {
        $filter = LogEntryFilterDTO::fromArray($request->validated());
        $result = $query->paginate($filename, $filter);

        return ApiResponse::success($result);
    }

    /**
     * Bulk delete one or more log files. Returns deleted + failed lists.
     */
    public function destroy(DeleteLogFilesRequest $request, DeleteLogFilesAction $action): ApiResponse
    {
        $dto = DeleteLogFilesDTO::fromArray($request->validated());
        $result = $action->execute($dto, Auth::id());

        return ApiResponse::success($result);
    }
}

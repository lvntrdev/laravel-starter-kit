<?php

namespace Lvntr\StarterKit\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Lvntr\StarterKit\Domain\ActivityLog\Queries\ActivityLogDatatableQuery;
use Lvntr\StarterKit\Http\Responses\ApiResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Admin panel activity log viewer.
 *
 * This controller is intentionally thin:
 *   - Read queries → Query
 */
class ActivityLogController extends Controller
{
    /**
     * Display the activity log listing page.
     */
    public function index(): Response
    {
        $subjectTypes = Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn (string $fqcn) => [
                'label' => class_basename($fqcn),
                'value' => $fqcn,
            ])
            ->sortBy('label')
            ->values()
            ->all();

        return Inertia::render('Admin/ActivityLogs/Index', [
            'subjectTypes' => $subjectTypes,
        ]);
    }

    /**
     * Return paginated activity logs as JSON for the DataTable component.
     */
    public function dtApi(ActivityLogDatatableQuery $query): ApiResponse
    {
        return $query->response();
    }

    /**
     * Return activity log detail as JSON for dialog usage.
     */
    public function show(Activity $activity): ApiResponse
    {
        $activity->load(['subject', 'causer']);

        return to_api($activity);
    }
}

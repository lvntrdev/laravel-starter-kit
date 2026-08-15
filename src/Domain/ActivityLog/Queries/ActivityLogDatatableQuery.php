<?php

namespace Lvntr\StarterKit\Domain\ActivityLog\Queries;

use Lvntr\StarterKit\Http\Responses\ApiResponse;
use Lvntr\StarterKit\Http\Responses\DatatableQueryBuilder;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Query: Build the activity log datatable response with eager-loaded relations.
 */
class ActivityLogDatatableQuery
{
    public function response(): ApiResponse
    {
        return DatatableQueryBuilder::for(
            Activity::query()->with(['subject', 'causer'])
        )
            ->searchable(['description', 'subject_type', 'subject_id', 'log_name'])
            ->sortable(['id', 'log_name', 'description', 'subject_type', 'event', 'created_at'])
            ->filterable([
                'log_name',
                'event',
                AllowedFilter::exact('subject_type'),
                ...DatatableQueryBuilder::dateRangeFilters('created_at'),
            ])
            ->defaultSort('-created_at')
            ->response();
    }
}

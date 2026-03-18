<?php

namespace App\Domain\ActivityLog\Queries;

use App\Http\Responses\ApiResponse;
use App\Http\Responses\DatatableQueryBuilder;
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
                AllowedFilter::callback('created_at_from', fn ($query, $value) => $query->whereDate('created_at', '>=', $value)),
                AllowedFilter::callback('created_at_to', fn ($query, $value) => $query->whereDate('created_at', '<=', $value)),
            ])
            ->defaultSort('-created_at')
            ->response();
    }
}

<?php

namespace App\Domain\SampleContent\Queries;

use App\Http\Resources\Admin\SampleContent\SampleContentResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\DatatableQueryBuilder;
use App\Models\SampleContent;
use App\Support\TranslatableQueryHelpers;

class SampleContentDatatableQuery
{
    public function response(): ApiResponse
    {
        return DatatableQueryBuilder::for(SampleContent::class)
            ->with(['user:id,first_name,last_name'])
            ->filterable([
                TranslatableQueryHelpers::searchFilter('title'),
            ])
            ->sortable([
                TranslatableQueryHelpers::localeSort('title'),
                'created_at',
                'updated_at',
            ])
            ->defaultSort('-created_at')
            ->resource(SampleContentResource::class)
            ->response();
    }
}

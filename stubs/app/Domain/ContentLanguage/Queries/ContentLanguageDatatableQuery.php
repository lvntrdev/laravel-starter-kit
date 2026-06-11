<?php

namespace App\Domain\ContentLanguage\Queries;

use App\Http\Resources\Admin\ContentLanguage\ContentLanguageResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\DatatableQueryBuilder;
use App\Models\ContentLanguage;

/**
 * Query: Build the content-language datatable response.
 *
 * Content languages drive the locale tabs of translatable content fields —
 * a separate concept from the admin UI locale. The list is ordered by
 * sort_order by default so the table mirrors the order the locale tabs
 * appear in.
 */
class ContentLanguageDatatableQuery
{
    public function response(): ApiResponse
    {
        return DatatableQueryBuilder::for(ContentLanguage::query())
            ->searchable(['code', 'name', 'native_name'])
            ->sortable([
                'id',
                'code',
                'name',
                'native_name',
                'direction',
                'is_active',
                'is_default',
                'sort_order',
                'created_at',
            ])
            ->filterable(['is_active'])
            ->columns([
                ['key' => 'code', 'locked' => true],
                'name',
                'native_name',
                'direction',
                'is_active',
                'is_default',
                'sort_order',
            ])
            ->alwaysInclude(['is_default'])
            ->defaultSort('sort_order')
            ->resource(ContentLanguageResource::class)
            ->response();
    }
}

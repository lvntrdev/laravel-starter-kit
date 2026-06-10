<?php

namespace Lvntr\StarterKit\Domain\User\Queries;

use App\Enums\RoleEnum;
use App\Http\Resources\Admin\User\UserResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\DatatableQueryBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Query: Build the user datatable response with role hierarchy filtering.
 *
 * Non-system_admin users can only see users whose highest role is at
 * the same level or below their own in the hierarchy (sort_order >= theirs).
 */
class UserDatatableQuery
{
    public function response(User $currentUser): ApiResponse
    {
        $query = User::query();

        if (! $currentUser->hasRole(RoleEnum::SystemAdmin)) {
            $userMinSortOrder = $currentUser->roles->min('sort_order');

            if ($userMinSortOrder === null) {
                // Actor has no role at all (e.g. direct-permission user) — the
                // lowest possible rank, so they may only see other role-less
                // users. Casting null → 0 would disable the hierarchy filter
                // entirely (no role has sort_order < 0).
                $query->whereDoesntHave('roles');
            } else {
                $query->whereDoesntHave('roles', function (Builder $q) use ($userMinSortOrder) {
                    $q->where('sort_order', '<', (int) $userMinSortOrder);
                });
            }
        }

        return DatatableQueryBuilder::for($query)
            ->searchable(['id', 'first_name', 'last_name', 'email'])
            ->sortable([
                'id',
                'first_name',
                'last_name',
                AllowedSort::field('full_name', 'first_name'),
                'email',
                'status',
                'created_at',
                'updated_at',
            ])
            ->columns([
                ['key' => 'full_name', 'locked' => true],
                'email',
                ['key' => 'role', 'sortable' => false],
                'status',
                'created_at',
                ['key' => 'updated_at', 'label' => 'sk-common.updated_at', 'visible' => false],
            ])
            ->alwaysInclude(['full_name', 'role_color'])
            ->filterable([
                'status',
                AllowedFilter::callback('role', function (Builder $q, $value) {
                    $q->whereHas('roles', fn (Builder $r) => $r->where('name', $value));
                }),
            ])
            ->with(['roles', 'media'])
            ->defaultSort('-created_at')
            ->resource(UserResource::class)
            ->response();
    }
}

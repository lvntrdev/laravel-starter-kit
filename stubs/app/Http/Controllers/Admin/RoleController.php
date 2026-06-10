<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Role\Actions\CreateRoleAction;
use App\Domain\Role\Actions\DeleteRoleAction;
use App\Domain\Role\Actions\SyncPermissionsAction;
use App\Domain\Role\Actions\UpdateRoleAction;
use App\Domain\Role\BulkActions\BulkDeleteRoleAction;
use App\Domain\Role\DTOs\RoleDTO;
use App\Domain\Role\Queries\CanManageRoleQuery;
use App\Domain\Role\Queries\GroupedPermissionsQuery;
use App\Domain\Role\Queries\RoleBulkSelectionQuery;
use App\Domain\Role\Queries\RoleDatatableQuery;
use App\Domain\Role\Queries\UserGrantablePermissionsQuery;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkActionRequest;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Http\Resources\Admin\Role\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lvntr\StarterKit\Http\Bulk\BulkActionDispatcher;

/**
 * Admin panel role management controller.
 *
 * This controller is intentionally thin:
 *   - Validation → FormRequest
 *   - Data mapping → DTO
 *   - Business logic → Action
 *   - Listing / filtering → Query
 */
class RoleController extends Controller
{
    /**
     * Display the role listing page.
     */
    public function index(): Response
    {
        $user = Auth::user();

        // null = actor has no role at all (e.g. direct-permission user); the
        // frontend treats it as the lowest possible rank. Casting null → 0
        // would mark every role as manageable in the UI.
        $userMinSortOrder = $user->roles->min('sort_order');

        return Inertia::render('Admin/Roles/Index', [
            'protectedRoles' => array_map(fn (RoleEnum $r) => $r->value, RoleEnum::cases()),
            'isSystemAdmin' => $user->hasRole(RoleEnum::SystemAdmin),
            'userMinSortOrder' => $userMinSortOrder === null ? null : (int) $userMinSortOrder,
        ]);
    }

    /**
     * Return paginated roles as JSON for the DataTable component.
     */
    public function dtApi(RoleDatatableQuery $query): ApiResponse
    {
        return $query->response();
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(GroupedPermissionsQuery $permissionsQuery, UserGrantablePermissionsQuery $grantableQuery): Response
    {
        return Inertia::render('Admin/Roles/Create', [
            'permissionsByGroup' => $permissionsQuery->get(),
            'availableLocales' => config('app.languages', ['en' => 'English']),
            'userPermissions' => $grantableQuery->get(Auth::user()),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request, CreateRoleAction $action): RedirectResponse
    {
        $action->execute(RoleDTO::fromArray($request->validated()));

        return redirect()
            ->route('roles.index')
            ->with('success', __('sk-message.created', ['entity' => __('sk-role.role')]));
    }

    /**
     * Return role data as JSON for dialog usage.
     *
     * Mirrors the row-level authorization that edit() / destroy() apply:
     * the route-level roles.read permission is not enough on its own —
     * a non-system_admin caller must also outrank the target role,
     * otherwise this endpoint would leak details of protected roles.
     */
    public function data(Role $role, CanManageRoleQuery $canManageQuery): ApiResponse
    {
        if (! $canManageQuery->check(Auth::user(), $role)) {
            abort(403);
        }

        $role->load('permissions');

        return to_api(new RoleResource($role));
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(
        Role $role,
        CanManageRoleQuery $canManageQuery,
        GroupedPermissionsQuery $permissionsQuery,
        UserGrantablePermissionsQuery $grantableQuery,
    ): Response {
        if (! $canManageQuery->check(Auth::user(), $role)) {
            abort(403);
        }

        $role->load('permissions');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => (new RoleResource($role))->resolve(),
            'permissionsByGroup' => $permissionsQuery->get(),
            'availableLocales' => config('app.languages', ['en' => 'English']),
            'userPermissions' => $grantableQuery->get(Auth::user()),
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role, UpdateRoleAction $action): RedirectResponse
    {
        $action->execute($role, RoleDTO::fromArray($request->validated()));

        return redirect()
            ->route('roles.index')
            ->with('success', __('sk-message.updated', ['entity' => __('sk-role.role')]));
    }

    /**
     * Sync permissions from config (runs RolePermissionSeeder).
     * Only accessible by system_admin users.
     */
    public function syncPermissions(SyncPermissionsAction $action): RedirectResponse
    {
        if (! Auth::user()->hasRole(RoleEnum::SystemAdmin)) {
            abort(403);
        }

        $action->execute();

        return redirect()
            ->route('roles.index')
            ->with('success', __('sk-message.permissions_synced'));
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role, DeleteRoleAction $action, CanManageRoleQuery $canManageQuery): RedirectResponse
    {
        if (! $canManageQuery->check(Auth::user(), $role)) {
            abort(403);
        }

        $action->execute($role, Auth::id());

        return redirect()
            ->route('roles.index')
            ->with('success', __('sk-message.deleted', ['entity' => __('sk-role.role')]));
    }

    /**
     * Handle a bulk action on role records.
     *
     * POST /admin/roles/bulk
     * Route name: roles.bulk
     *
     * Two selection modes:
     *   - page (default): operate on the explicit `ids` the client sent.
     *   - select_all_filtered: re-resolve the FULL set matching the active
     *     filter snapshot via RoleBulkSelectionQuery. Roles are listed to every
     *     roles.read actor (no datatable visibility scope by design), so the
     *     hierarchy is NOT applied at query time here — it is enforced per item
     *     by BulkDeleteRoleAction::authorize() (system roles protected, and a
     *     non-system_admin may only delete roles ranked below their own),
     *     exactly as the ids path already relies on.
     */
    public function bulk(
        BulkActionRequest $request,
        BulkDeleteRoleAction $bulkDelete,
        RoleBulkSelectionQuery $selectionQuery,
    ): RedirectResponse {
        $dispatcher = new BulkActionDispatcher;
        $dispatcher->register($bulkDelete);

        $actionKey = $request->validated('action');

        if (! $dispatcher->has($actionKey)) {
            return back()->with('error', __('sk-bulk.unsupported_action', ['action' => $actionKey]));
        }

        $actor = $request->user();
        $actor->loadMissing('roles');

        $capReached = false;

        if ($request->boolean('select_all_filtered')) {
            $items = $selectionQuery->resolve($request->validated('filter_snapshot') ?? []);

            // The cross-page query caps at MAX_ITEMS (no silent caps): if the
            // resolved set hit that bound, the filter matched more rows than a
            // single bulk operation processes — warn the user so the untouched
            // remainder is not mistaken for "done".
            $capReached = $items->count() === RoleBulkSelectionQuery::MAX_ITEMS;
        } else {
            $items = Role::query()
                ->whereIn('id', $request->validated('ids'))
                ->get();
        }

        $result = $dispatcher->dispatch($actor, $actionKey, $items);

        $response = back()->with('success', $result['message']);

        if ($capReached) {
            $response->with('warning', __('sk-bulk.cap_reached', [
                'max' => RoleBulkSelectionQuery::MAX_ITEMS,
            ]));
        }

        return $response;
    }
}

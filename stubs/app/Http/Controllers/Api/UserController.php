<?php

namespace App\Http\Controllers\Api;

use App\Domain\User\Actions\CreateUserAction;
use App\Domain\User\Actions\DeleteUserAction;
use App\Domain\User\Actions\UpdateUserAction;
use App\Domain\User\DTOs\UserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\StoreUserRequest;
use App\Http\Requests\Api\User\UpdateUserRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\DatatableQueryBuilder;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * REST API controller for user management (mobile / external clients).
 *
 * All responses follow the standard ApiResponse envelope.
 * Authentication: Passport (auth:api).
 */
class UserController extends Controller
{
    /**
     * List users with search, sort, filters and pagination.
     *
     * GET /api/users?filter[status]=active&sort=-created_at&per_page=15
     */
    public function index(): ApiResponse
    {
        return DatatableQueryBuilder::for(User::class)
            ->searchable(['name', 'email'])
            ->sortable(['id', 'name', 'email', 'status', 'created_at'])
            ->filterable(['status'])
            ->defaultSort('-created_at')
            ->response();
    }

    /**
     * Create a new user.
     *
     * POST /api/users
     */
    public function store(StoreUserRequest $request, CreateUserAction $action): ApiResponse
    {
        $dto = UserDTO::fromArray($request->validated());
        $user = $action->execute($dto);

        return to_api($user, 'User created successfully.', 201);
    }

    /**
     * Show a single user.
     *
     * GET /api/users/{user}
     */
    public function show(User $user): ApiResponse
    {
        return to_api($user);
    }

    /**
     * Update an existing user.
     *
     * PUT/PATCH /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): ApiResponse
    {
        $dto = UserDTO::fromArray($request->validated());
        $user = $action->execute($user, $dto);

        return to_api($user, 'User updated successfully.');
    }

    /**
     * Delete a user.
     *
     * DELETE /api/users/{user}
     */
    public function destroy(User $user, DeleteUserAction $action): ApiResponse|JsonResponse
    {
        try {
            $action->execute($user, auth()->id());

            return to_api(status: 204);
        } catch (\LogicException $e) {
            return to_api(null, $e->getMessage(), 400);
        }
    }
}

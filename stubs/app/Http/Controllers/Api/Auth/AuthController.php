<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Auth\Actions\LoginUserAction;
use App\Domain\Auth\Actions\LogoutUserAction;
use App\Domain\Auth\Actions\RegisterUserAction;
use App\Domain\Auth\DTOs\LoginDTO;
use App\Domain\Auth\DTOs\RegisterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API authentication controller.
 *
 * This controller is intentionally thin:
 *   - Validation → FormRequest
 *   - Data mapping → DTO
 *   - Business logic → Action
 */
class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request, RegisterUserAction $action): ApiResponse
    {
        $result = $action->execute(RegisterDTO::fromArray($request->validated()));

        return to_api($result, 'Registration successful.', 201);
    }

    /**
     * Log in a user.
     */
    public function login(LoginRequest $request, LoginUserAction $action): ApiResponse
    {
        $result = $action->execute(LoginDTO::fromArray($request->validated()));

        if (! $result) {
            return to_api(null, 'Invalid email or password.', 401);
        }

        return to_api($result, 'Login successful.');
    }

    /**
     * Log out — revoke the current access token.
     */
    public function logout(Request $request, LogoutUserAction $action): ApiResponse|JsonResponse
    {
        $action->execute($request->user());

        return to_api(message: 'Logged out.');
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): ApiResponse
    {
        return to_api($request->user());
    }
}

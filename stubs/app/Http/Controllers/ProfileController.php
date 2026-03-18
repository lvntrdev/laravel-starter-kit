<?php

namespace App\Http\Controllers;

use App\Domain\Media\Actions\ClearMediaAction;
use App\Domain\Media\Actions\UploadMediaAction;
use App\Domain\Session\Actions\PurgeOtherSessionsAction;
use App\Domain\Session\Queries\UserSessionsQuery;
use App\Http\Requests\DestroySessionsRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User profile controller.
 *
 * Handles profile page, avatar, logout, and browser sessions.
 *
 * This controller is intentionally thin:
 *   - Validation → FormRequest
 *   - Read queries → Query
 *   - Business logic → Action
 */
class ProfileController extends Controller
{
    /**
     * Display the profile page.
     */
    public function index(): Response
    {
        $user = Auth::user();

        return Inertia::render('Profile/Index', [
            'twoFactorEnabled' => ! is_null($user->two_factor_secret),
            'twoFactorConfirmed' => ! is_null($user->two_factor_confirmed_at),
        ]);
    }

    /**
     * Log the user out and invalidate the session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Upload avatar for the authenticated user.
     */
    public function uploadAvatar(UploadAvatarRequest $request, UploadMediaAction $action): ApiResponse
    {
        $user = $request->user();
        $action->execute($user, $request, 'avatar');

        return to_api(['avatar_url' => $user->refresh()->avatar_url], 'Avatar uploaded successfully.');
    }

    /**
     * Delete avatar for the authenticated user.
     */
    public function deleteAvatar(Request $request, ClearMediaAction $action): ApiResponse
    {
        $action->execute($request->user(), 'avatar');

        return to_api(status: 204);
    }

    /**
     * Get the current browser sessions for the authenticated user.
     */
    public function sessions(Request $request, UserSessionsQuery $query): ApiResponse
    {
        return to_api($query->get(
            $request->user()->getAuthIdentifier(),
            $request->session()->getId(),
        ));
    }

    /**
     * Log out from other browser sessions.
     */
    public function destroySessions(DestroySessionsRequest $request, PurgeOtherSessionsAction $action): ApiResponse
    {
        $action->execute(
            $request->user(),
            $request->input('password'),
            $request->session()->getId(),
        );

        return to_api(null, __('Other browser sessions have been logged out.'));
    }
}

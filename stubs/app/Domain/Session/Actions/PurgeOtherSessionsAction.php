<?php

namespace App\Domain\Session\Actions;

use App\Domain\Shared\Actions\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Action: Delete all other browser sessions for the authenticated user.
 * Validates the user's password before proceeding.
 */
class PurgeOtherSessionsAction extends BaseAction
{
    /**
     * Execute the action.
     *
     * @throws ValidationException
     */
    public function execute(User $user, string $password, string $currentSessionId): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The provided password is incorrect.')],
            ]);
        }

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}

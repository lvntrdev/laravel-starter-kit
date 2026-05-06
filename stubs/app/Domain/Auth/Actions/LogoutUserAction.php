<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Revoke the current API access token for a user.
 */
class LogoutUserAction extends BaseAction
{
    public function execute(User $user): void
    {
        $user->token()?->revoke();
    }
}

<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Shared\Actions\BaseAction;
use App\Models\User;

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

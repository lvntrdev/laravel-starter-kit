<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\LoginDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Action: Authenticate a user via API credentials and create an access token.
 *
 * @return array{user: User, token: string}|null Returns null on failed authentication.
 */
class LoginUserAction extends BaseAction
{
    /**
     * @return array{user: User, token: string}|null
     */
    public function execute(LoginDTO $dto): ?array
    {
        if (! Auth::attempt($dto->credentials())) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth-token')->accessToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}

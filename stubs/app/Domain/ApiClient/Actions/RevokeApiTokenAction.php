<?php

namespace App\Domain\ApiClient\Actions;

use Illuminate\Support\Facades\Log;
use Laravel\Passport\Token;

/**
 * Bir Personal Access Token'ı revoke eder.
 *
 * Privilege escalation notu: bu action'ı çağırmadan önce ApiTokenPolicy::delete()
 * kontrolü yapılmalıdır. Policy kontrolü olmadan çağrılırsa herhangi bir token
 * revoke edilebilir.
 */
class RevokeApiTokenAction
{
    public function execute(Token $token): void
    {
        $token->refreshToken?->revoke();
        $token->revoke();

        Log::info('API token revoke edildi.', [
            'token_id' => $token->id,
            'user_id' => $token->user_id,
        ]);
    }
}

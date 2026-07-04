<?php

namespace Lvntr\StarterKit\Domain\ApiClient\Actions;

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

        // Audit sink (Task 8): revoke edilen token admin ActivityLog UI'ında
        // görünür. Yalnız kimliği doğrulanmış aktör varken kaydedilir.
        //
        // Token subject OLARAK kullanılmaz: oauth_access_tokens.id char(80) iken
        // activity_log.subject_id char(36) → performedOn strict SQL'de patlar.
        // Kimlik properties.token_id'de tutulur.
        if (auth()->check()) {
            activity('audit')
                ->event('deleted')
                ->withProperties([
                    'token_id' => $token->id,
                    'user_id' => $token->user_id,
                ])
                ->log('API token revoked');
        }
    }
}

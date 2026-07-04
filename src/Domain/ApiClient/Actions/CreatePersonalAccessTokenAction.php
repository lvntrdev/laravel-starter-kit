<?php

namespace Lvntr\StarterKit\Domain\ApiClient\Actions;

use App\Models\User;
use Laravel\Passport\PersonalAccessTokenResult;

/**
 * Belirtilen kullanıcı için Personal Access Token (PAT) oluşturur.
 *
 * Secret handling: `accessToken` değeri yalnızca bu action'ın döndürdüğü
 * `PersonalAccessTokenResult`'ta bulunur. DB'de hashlenmiş olarak saklanır.
 * Log'a, activity properties'e, session'a veya response dışı herhangi bir
 * alana yazılmamalıdır.
 */
class CreatePersonalAccessTokenAction
{
    /**
     * @param  string[]  $scopes
     */
    public function execute(User $user, string $name, array $scopes = []): PersonalAccessTokenResult
    {
        $result = $user->createToken($name, $scopes);

        // Audit sink (Task 8): oluşturulan token admin ActivityLog UI'ında
        // görünür. accessToken KASITLI olarak properties'e alınmaz. Kayıt
        // yalnız kimliği doğrulanmış aktör varken atılır.
        //
        // Token subject OLARAK kullanılmaz: oauth_access_tokens.id char(80) iken
        // activity_log.subject_id char(36). performedOn($token) strict SQL'de
        // truncation/hata verir (token zaten üretildikten SONRA → 500 + tek-
        // seferlik plaintext token kaybı). Kimlik properties.token_id'de tutulur.
        if (auth()->check()) {
            activity('audit')
                ->causedBy($user)
                ->event('created')
                ->withProperties([
                    'token_id' => $result->token->id,
                    'user_id' => $user->id,
                    'name' => $name,
                    'scopes' => $scopes,
                ])
                ->log('Personal access token created');
        }

        return $result;
    }
}

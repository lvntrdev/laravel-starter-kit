<?php

namespace Lvntr\StarterKit\Domain\ApiClient\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\PersonalAccessTokenResult;

/**
 * Belirtilen kullanıcı için Personal Access Token (PAT) oluşturur.
 *
 * Secret handling: `accessToken` değeri yalnızca bu action'ın döndürdüğü
 * `PersonalAccessTokenResult`'ta bulunur. DB'de hashlenmiş olarak saklanır.
 * Log'a, session'a veya response dışı herhangi bir alana yazılmamalıdır.
 */
class CreatePersonalAccessTokenAction
{
    /**
     * @param  string[]  $scopes
     */
    public function execute(User $user, string $name, array $scopes = []): PersonalAccessTokenResult
    {
        $result = $user->createToken($name, $scopes);

        // access token kasıtlı olarak loglanmıyor
        Log::info('Personal access token oluşturuldu.', [
            'token_id' => $result->token->id,
            'user_id' => $user->id,
            'name' => $name,
            'scopes' => $scopes,
        ]);

        return $result;
    }
}

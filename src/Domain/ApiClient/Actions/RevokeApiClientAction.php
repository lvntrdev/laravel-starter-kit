<?php

namespace Lvntr\StarterKit\Domain\ApiClient\Actions;

use Laravel\Passport\Client;

/**
 * Passport OAuth istemcisini ve ilişkili tüm token'larını revoke eder.
 *
 * Bu işlem geri alınamaz. İstemci ve token'lar DB'den silinmez;
 * `revoked = true` olarak işaretlenir.
 */
class RevokeApiClientAction
{
    public function execute(Client $client): void
    {
        // İlişkili tüm access token'ları ve refresh token'ları revoke et
        $client->tokens()->with('refreshToken')->each(function ($token): void {
            $token->refreshToken?->revoke();
            $token->revoke();
        });

        // İstemciyi revoke et
        $client->forceFill(['revoked' => true])->save();

        // Audit sink (Task 8): revoke edilen istemci admin ActivityLog UI'ında
        // görünür. Yalnız kimliği doğrulanmış aktör varken kaydedilir.
        if (auth()->check()) {
            activity('audit')
                ->performedOn($client)
                ->event('deleted')
                ->withProperties([
                    'client_id' => $client->id,
                    'name' => $client->name,
                ])
                ->log('OAuth client revoked');
        }
    }
}

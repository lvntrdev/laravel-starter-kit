<?php

namespace App\Domain\ApiClient\Actions;

use Laravel\Passport\Client;

/**
 * Mevcut bir Passport OAuth istemcisini günceller.
 *
 * Grant type değiştirilmez — değişim için revoke + yeni client gerekir.
 */
class UpdateApiClientAction
{
    /**
     * @param  string[]  $redirectUris
     * @param  string[]  $scopes
     */
    public function execute(
        Client $client,
        string $name,
        array $redirectUris = [],
        array $scopes = [],
    ): Client {
        $client->forceFill([
            'name' => $name,
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
        ])->save();

        return $client->fresh();
    }
}

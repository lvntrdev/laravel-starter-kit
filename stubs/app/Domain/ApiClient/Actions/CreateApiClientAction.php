<?php

namespace App\Domain\ApiClient\Actions;

use Illuminate\Support\Facades\Log;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Yeni bir Passport OAuth istemcisi oluşturur.
 *
 * Secret handling: `ClientRepository::create*()` metodları Client'a `plainSecret`
 * set eder. Bu değer yalnızca bu action'ın response'unda bir kez bulunabilir.
 * Log'a, session'a veya herhangi bir kalıcı depolama alanına yazılmamalıdır.
 */
class CreateApiClientAction
{
    public function __construct(
        private readonly ClientRepository $clients,
    ) {}

    /**
     * @param  string[]  $redirectUris
     *
     * Güvenlik notu: $confidential parametresi kaldırıldı. Her client zorunlu olarak
     * confidential=true ile oluşturulur. Public (PKCE) client desteği bu UI üzerinden
     * verilmez — güvenli default prensibi.
     */
    public function execute(
        string $name,
        string $grantType,
        array $redirectUris = [],
    ): Client {
        $client = match ($grantType) {
            'authorization_code' => $this->clients->createAuthorizationCodeGrantClient(
                $name,
                $redirectUris,
                confidential: true,
            ),
            'client_credentials' => $this->clients->createClientCredentialsGrantClient($name),
            default => throw new \InvalidArgumentException("Geçersiz grant tipi: {$grantType}"),
        };

        // plainSecret sadece bu response'ta mevcut.
        // UYARI: bu değer log'a yazılmamalı.
        Log::info('Yeni OAuth istemcisi oluşturuldu.', [
            'client_id' => $client->id,
            'name' => $client->name,
            'grant_type' => $grantType,
            // secret kasıtlı olarak loglanmıyor
        ]);

        return $client;
    }
}

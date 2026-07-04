<?php

namespace Lvntr\StarterKit\Domain\ApiClient\Actions;

use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Yeni bir Passport OAuth istemcisi oluşturur.
 *
 * Secret handling: `ClientRepository::create*()` metodları Client'a `plainSecret`
 * set eder. Bu değer yalnızca bu action'ın response'unda bir kez bulunabilir.
 * Log'a, activity properties'e, session'a veya herhangi bir kalıcı depolama
 * alanına yazılmamalıdır.
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

        // Audit sink (Task 8): admin ActivityLog UI'ında görünür kayıt.
        // plainSecret sadece bu response'ta mevcut ve KASITLI olarak
        // properties'e alınmaz. Kayıt yalnız kimliği doğrulanmış bir aktör
        // varken atılır (auto-resolved causer); seeder/console yolları bir
        // admin eylemi değildir.
        if (auth()->check()) {
            activity('audit')
                ->performedOn($client)
                ->event('created')
                ->withProperties([
                    'client_id' => $client->id,
                    'name' => $client->name,
                    'grant_type' => $grantType,
                ])
                ->log('OAuth client created');
        }

        return $client;
    }
}

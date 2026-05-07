<?php

namespace App\Http\Resources\Admin\ApiToken;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Laravel\Passport\Token;

/**
 * @mixin Token
 *
 * Token handling:
 *   - `accessToken` string'i yalnızca `PersonalAccessTokenResult` üzerinden
 *     create sırasında erişilebilir. Token model'i plaintext tutmaz.
 *   - Bu resource, `StoreApiTokenResponse` tarafından `accessToken` explicitly
 *     set edilerek döndürülür. Listele/görüntüle durumlarında `access_token`
 *     alanı null gelir — bu kasıtlı ve güvenlik gereğidir.
 *
 * User ilişkisi:
 *   - `Passport\Token::user()` ilişkisi `$this->client->provider`'a bakar; fresh
 *     model üzerinde Eloquent relation tipi çıkarımı sırasında null erişimi
 *     fırlatır. Bu yüzden `with(['user'])` kullanılamaz; `collection()` içinde
 *     user'lar tek query ile toplu önçekilip `setRelation('user', ...)` ile
 *     bağlanır.
 */
class ApiTokenResource extends JsonResource
{
    /**
     * Yalnızca create response'unda plaintext token taşır.
     * Diğer durumlarda bu field null'dır.
     */
    public ?string $accessToken = null;

    /**
     * Collection üretilmeden önce tüm token'ların user ilişkisini tek query
     * ile yükler. Passport Token::user() bug'ını bypass eder.
     */
    public static function collection($resource): ResourceCollection
    {
        if ($resource !== null && method_exists($resource, 'pluck')) {
            $userIds = $resource->pluck('user_id')->filter()->unique()->all();

            if ($userIds !== []) {
                $users = User::query()->whereKey($userIds)->get()->keyBy(fn ($u) => $u->getKey());

                foreach ($resource as $token) {
                    if ($token instanceof Token && $token->user_id !== null) {
                        $token->setRelation('user', $users->get($token->user_id));
                    }
                }
            }
        }

        return parent::collection($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scopes' => $this->scopes ?? [],
            'revoked' => $this->revoked,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => format_date($this->created_at),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->getKey(),
                'full_name' => $this->user->full_name ?? null,
                'email' => $this->user->email ?? null,
            ] : null),

            // Yalnızca create endpoint'inde set edilir; listede null gelir.
            // Frontend "kopyala ve sakla" modal'ı için kullanılır.
            'access_token' => $this->accessToken,
        ];
    }
}

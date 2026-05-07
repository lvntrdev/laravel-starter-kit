<?php

namespace App\Http\Resources\Admin\ApiClient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passport\Client;

/**
 * @mixin Client
 *
 * Secret handling:
 *   - `plainSecret` yalnızca yeni istemci oluşturulduğunda Passport tarafından
 *     set edilir. Sonraki request'lerde null gelir.
 *   - `secret` alanı Client::$hidden listesinde — Passport model seviyesinde gizler.
 *   - Response'ta yalnızca `plainSecret` plaintext döner (create'ten hemen sonra).
 *   - Log ve audit trail'e secret yazan kod bulunmamalıdır (T12 security review odağı).
 *
 * Personal access client:
 *   - Passport 12+ ile `personal_access_client` boolean kolonu kaldırıldı; bilgi
 *     `grant_types` JSON kolonunda `personal_access` değerinin varlığıyla tutulur.
 *   - `preventAccessingMissingAttributes()` aktifken eski kolon adına erişim
 *     `MissingAttributeException` fırlatır — bu yüzden `grant_types` üzerinden okunur.
 */
class ApiClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $grantTypes = $this->grant_types ?? [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'grant_types' => $grantTypes,
            'redirect_uris' => $this->redirect_uris ?? [],
            'scopes' => $this->scopes ?? [],
            'revoked' => $this->revoked,
            'personal_access_client' => in_array('personal_access', $grantTypes, true),
            'confidential' => $this->secret !== null,
            'created_at' => format_date($this->created_at),
            'updated_at' => format_date($this->updated_at),

            // plainSecret yalnızca Passport'un create sırasında set ettiği
            // geçici değerdir. Sonraki okuma isteklerinde null gelir — bu kasıtlı.
            // Frontend "kopyala ve sakla" UX için kullanır; tekrar gösterilmez.
            'plain_secret' => $this->plainSecret,
        ];
    }
}

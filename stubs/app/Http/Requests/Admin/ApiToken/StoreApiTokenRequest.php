<?php

namespace App\Http\Requests\Admin\ApiToken;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for creating a new Personal Access Token.
 *
 * Secret leak riski: token plaintext yalnızca bu endpoint'in response'unda
 * bir kez döner. Log, audit trail veya başka response'ta yer almaz.
 *
 * Privilege escalation koruması: user_id kabul edilmez. Token her zaman
 * $request->user() için üretilir. Başka kullanıcı adına PAT üretme ihtiyacı
 * varsa admin.users.{user}.tokens.store gibi ayrı bir endpoint açılmalıdır.
 *
 * Scope escalation koruması: scopes alanı config('starter-kit.passport.scopes')
 * kataloğuna allow-list'lenir — tanımsız/keyfi scope (örn. `*`) ile token
 * üretilemez. Passport `createToken()` scope'ları doğrulamadan kabul ettiği
 * için tek enforcement noktası bu request'tir.
 *
 * İleri sıkılaştırma notu (bilinçli olarak bu kapsamda değil): allow-list,
 * scope'un *tanımlı* olmasını garanti eder ama kullanıcının o scope'u vermeye
 * *yetkili* olduğunu kontrol etmez. `api-tokens.create` izni olan herkes
 * katalogtaki `admin` gibi yüksek-yetki scope'larını da isteyebilir. Daha sıkı
 * model isteniyorsa scope→permission eşlemesi (örn. `admin` scope'u yalnızca
 * `admin` rolündeki kullanıcıya) withValidator() içinde ayrıca doğrulanmalıdır.
 */
class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('api-tokens.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Allow-list: yalnızca config kataloğunda tanımlı scope'lar istenebilir.
        // `*` Passport'ta hardcoded wildcard'dır (Token::can() `*` içeren token
        // için her kontrolde true döner) — consumer config'e `*` eklemiş olsa
        // bile request üzerinden asla kabul edilmez. `*` vermek bilinçli,
        // kod-seviyesi bir karar olmalıdır; bu satırı gevşetmeyin.
        //
        // Config boşsa (consumer publish edip scopes'u silmişse) Rule::in([])
        // gönderilen her scope'u reddeder — bu kasıtlıdır: scope kataloğu
        // tanımlı değilken scope'lu token üretilememeli. `scopes` nullable
        // olduğundan scope'suz istekler etkilenmez.
        $allowedScopes = array_diff(
            array_keys((array) config('starter-kit.passport.scopes', [])),
            ['*'],
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:255', Rule::in($allowedScopes)],
        ];
    }
}

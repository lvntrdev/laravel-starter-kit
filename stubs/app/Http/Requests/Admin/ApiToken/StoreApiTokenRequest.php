<?php

namespace App\Http\Requests\Admin\ApiToken;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules for creating a new Personal Access Token.
 *
 * Secret leak riski: token plaintext yalnızca bu endpoint'in response'unda
 * bir kez döner. Log, audit trail veya başka response'ta yer almaz.
 *
 * Privilege escalation koruması: user_id kabul edilmez. Token her zaman
 * $request->user() için üretilir. Başka kullanıcı adına PAT üretme ihtiyacı
 * varsa admin.users.{user}.tokens.store gibi ayrı bir endpoint açılmalıdır.
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:255'],
        ];
    }
}

<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\ApiClient;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lvntr\StarterKit\Rules\HttpsOrLocalhostUrl;

/**
 * Validation rules for creating a new Passport OAuth client.
 *
 * Güvenlik notları:
 *   - confidential alanı kabul edilmez; client her zaman confidential=true oluşturulur.
 *     Client Credentials ve Authorization Code grant'ı için public (PKCE) client üretimi
 *     UI üzerinden engellenmiştir.
 *   - personal_access grant_type UI'dan kaldırıldı; artisan komutuyla kurulur.
 *   - redirect_uris: authorization_code için zorunlu, sadece HTTPS (localhost istisnası ile).
 */
class StoreApiClientRequest extends FormRequest
{
    /**
     * Defense in depth — check.permission middleware de bu rotayı korur;
     * FormRequest doğrudan izni zorunlu kılar, middleware yanlış yapılandırılsa bile.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('api-clients.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'grant_type' => [
                'required',
                'string',
                Rule::in(['authorization_code', 'client_credentials']),
            ],
            // authorization_code grant'ı için en az bir redirect_uri zorunlu.
            // Diğer grant type'larda redirect_uri anlamsız; boş array kabul edilir.
            'redirect_uris' => [
                'required_if:grant_type,authorization_code',
                'nullable',
                'array',
                Rule::when(
                    fn () => $this->input('grant_type') === 'authorization_code',
                    ['min:1'],
                ),
            ],
            // Her URI sadece HTTPS veya localhost http'ye izin verilir (OAuth phishing koruması).
            'redirect_uris.*' => ['string', new HttpsOrLocalhostUrl, 'max:2048'],
            // confidential: kasıtlı olarak kabul edilmiyor.
            // CreateApiClientAction her zaman confidential=true zorlar.
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grant_type.in' => __('sk-api-clients.validation.grant_type_invalid'),
            'redirect_uris.required_if' => __('validation.required'),
            'redirect_uris.min' => __('validation.min.array', ['min' => 1]),
        ];
    }
}

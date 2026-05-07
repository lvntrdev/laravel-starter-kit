<?php

namespace App\Http\Requests\Admin\ApiClient;

use App\Rules\HttpsOrLocalhostUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Passport\Client;

/**
 * Validation rules for updating an existing Passport OAuth client.
 *
 * redirect_uris: StoreApiClientRequest ile aynı kural setini uygular.
 * authorization_code client'ında güncelleme sırasında da en az bir URI zorunludur.
 */
class UpdateApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('api-clients.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        // authorization_code client'larında redirect_uris zorunlu kalır
        $isAuthCode = $client && in_array('authorization_code', (array) ($client->grant_types ?? []), strict: true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => array_filter([
                $isAuthCode ? 'required' : 'nullable',
                'array',
                $isAuthCode ? 'min:1' : null,
            ]),
            // Her URI sadece HTTPS veya localhost http'ye izin verilir
            'redirect_uris.*' => ['string', new HttpsOrLocalhostUrl, 'max:2048'],
        ];
    }
}

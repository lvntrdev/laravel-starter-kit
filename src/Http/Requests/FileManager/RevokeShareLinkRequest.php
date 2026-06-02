<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Http\Requests\FileManager;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Share link revoke isteği.
 *
 * token: Signature parametresinin SHA256 hash'i.
 * CreateShareLinkAction'ın döndürdüğü ShareLinkResultDTO.tokenHash
 * alanından alınır.
 */
class RevokeShareLinkRequest extends FormRequest
{
    /**
     * K2 / O3 (security): Request katmanında erken yetki kontrolü.
     *
     * Yetki, controller ile AYNI gate üzerinden (`revoke-share-media` →
     * MediaPolicy) değerlendirilir; iki katman defense-in-depth sağlar ama
     * yetki mantığı tek kaynaktan gelir. Owner-only yerine context-aware:
     * kendi dosyası VEYA dosyanın context izni yeterlidir.
     *
     * media_id henüz validate edilmemişse (invalid integer) ya da kayıt
     * yoksa false döner ve Laravel 403 üretir. Böylece existence probe
     * saldırıları da önlenir (kayıt yoksa 403, hata ayrıntısı sızdırılmaz).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $mediaId = $this->input('media_id');

        if (! is_numeric($mediaId) || (int) $mediaId < 1) {
            // Validation kuralı bunu zaten yakalar; burada false dönmek güvenli.
            return false;
        }

        /** @var Media|null $media */
        $media = Media::find((int) $mediaId);

        if ($media === null) {
            return false;
        }

        return $user->can('revoke-share-media', $media);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'string', 'size:64'], // SHA256 hex = 64 karakter
        ];
    }
}

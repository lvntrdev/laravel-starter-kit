<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Http\Requests\FileManager;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Yeni share link oluşturma isteği.
 *
 * expires_in_hours: TTL saat cinsinden (1 ile max_ttl_hours arasında).
 * max_ttl_hours config'den okunur (varsayılan 720 = 30 gün).
 */
class CreateShareLinkRequest extends FormRequest
{
    /**
     * O3 (security): Request katmanında erken yetki kontrolü.
     *
     * Yetki, controller ile AYNI gate üzerinden (`share-media` → MediaPolicy)
     * değerlendirilir; böylece iki katman defense-in-depth sağlar ama yetki
     * mantığı tek kaynaktan gelir ve diverge edemez. Owner-only yerine
     * context-aware: kendi dosyası VEYA dosyanın context izni (örn. global
     * bucket'ta files.update) yeterlidir.
     *
     * media_id henüz validate edilmemişse (invalid integer) ya da kayıt
     * yoksa false döner ve Laravel 403 üretir.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $mediaId = $this->input('media_id');

        if (! is_numeric($mediaId) || (int) $mediaId < 1) {
            return false;
        }

        /** @var Media|null $media */
        $media = Media::find((int) $mediaId);

        if ($media === null) {
            return false;
        }

        return $user->can('share-media', $media);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('file-manager.share.max_ttl_hours', 720);

        return [
            'media_id' => ['required', 'integer', 'min:1'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', "max:{$max}"],
        ];
    }
}

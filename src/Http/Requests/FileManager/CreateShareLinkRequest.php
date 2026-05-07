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
     * O3 (security): Request katmanında erken ownership kontrolü.
     *
     * media_id geçerli ve mevcut kullanıcıya ait olmalıdır. Bu kontrolün
     * hem burada hem controller'da (Gate::authorize) yapılması defense-in-depth
     * sağlar: birinden atlanırsa diğeri yetki ihlalini yakalar.
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

        return (string) $media->model_id === (string) $user->getAuthIdentifier()
            && $media->model_type === $user->getMorphClass();
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

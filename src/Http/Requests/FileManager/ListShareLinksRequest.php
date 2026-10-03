<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Http\Requests\FileManager;

use Illuminate\Foundation\Http\FormRequest;
use Lvntr\StarterKit\Domain\FileManager\Concerns\ResolvesMediaModel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Bir medyanın aktif share link listesi isteği.
 *
 * Liste, iptal yetkisi olan kullanıcıya açıktır; bu yüzden yetki
 * RevokeShareLinkRequest ile AYNI `revoke-share-media` gate'inden gelir.
 */
class ListShareLinksRequest extends FormRequest
{
    use ResolvesMediaModel;

    /**
     * Eksik, sayısal olmayan veya bilinmeyen media_id için false döner
     * (403): existence oracle yok. withTrashed lookup revoke ile tutarlı —
     * çöp kutusundaki medyanın linkleri de listelenip iptal edilebilmeli.
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
        $media = $this->mediaQueryWithTrashed()->find((int) $mediaId);

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
        ];
    }
}

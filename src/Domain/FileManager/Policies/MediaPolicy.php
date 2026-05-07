<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * FileManager Media politikaları.
 *
 * share / revokeShare: owner-only kontrolü.
 * Admin kullanıcılar Gate::before ile zaten tüm policy'leri atlatır;
 * bu policy non-admin kullanıcılar için ownership'i zorlar.
 *
 * Ownership tespiti: media.model_id == user.id VE
 * media.model_type == user morph alias.
 * Bu yöntem context-agnostic — GlobalFileBucket gibi shared context'lerde
 * farklı ownership mantığı gerekiyorsa policy override edilmeli.
 *
 * TODO (multi-tenant): Tenant izolasyonu host uygulamanın middleware'ine
 * bırakılmıştır. Tenant context'i ShareController@show'da aktif olmalıdır.
 */
class MediaPolicy
{
    /**
     * Kullanıcı bu medya için share link üretebilir mi?
     */
    public function share(Authenticatable $user, Media $media): bool
    {
        if (! config('file-manager.share.enabled', true)) {
            return false;
        }

        return $this->isOwner($user, $media);
    }

    /**
     * Kullanıcı bu medyanın share link'ini revoke edebilir mi?
     */
    public function revokeShare(Authenticatable $user, Media $media): bool
    {
        if (! config('file-manager.share.enabled', true)) {
            return false;
        }

        if (! config('file-manager.share.allow_revoke', true)) {
            return false;
        }

        return $this->isOwner($user, $media);
    }

    /**
     * Medya kaydının bu kullanıcıya ait olup olmadığını kontrol eder.
     * model_type morph alias veya FQCN karşılaştırması yapar.
     */
    private function isOwner(Authenticatable $user, Media $media): bool
    {
        return (string) $media->model_id === (string) $user->getAuthIdentifier()
            && $media->model_type === $user->getMorphClass();
    }
}

<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * FileManager Media politikaları.
 *
 * share / revokeShare: medyayı YÖNETME yetkisi kontrolü.
 * Admin kullanıcılar Gate::before ile zaten tüm policy'leri atlatır;
 * bu policy non-admin kullanıcılar için yetkiyi zorlar.
 *
 * Yetki tespiti iki aşamalı (bkz. {@see self::canManage()}):
 *   1. Owner fast-path — media.model_id == user.id VE model_type == user
 *      morph alias ise kullanıcı kendi dosyasını yönetir, izin verilir.
 *   2. Aksi halde dosyanın FileManager context'inin kendi authorizer'ına
 *      delege edilir. Böylece GlobalFileBucket gibi paylaşımlı context'ler,
 *      kişisel ownership yerine context iznine (örn. files.update) göre
 *      paylaşılabilir hale gelir — owner-only kontrolünün her zaman 403
 *      döndürmesi sorunu (global dosyalar) ortadan kalkar.
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

        return $this->canManage($user, $media);
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

        return $this->canManage($user, $media);
    }

    /**
     * Kullanıcı bu medyayı paylaşma/revoke amacıyla yönetebilir mi?
     *
     * 1. Owner ise (kendi dosyası) her zaman izinli — registry'ye bağımlı
     *    olmadan kişisel `user` context'ini kapsar.
     * 2. Aksi halde dosyanın context'i çözülür ve o context'in authorizer'ına
     *    'update' yetkisiyle delege edilir (global bucket → files.update).
     *    Context çözülemezse veya owner kaydı bulunamazsa güvenli tarafta
     *    kalınır ve reddedilir.
     */
    private function canManage(Authenticatable $user, Media $media): bool
    {
        if ($this->isOwner($user, $media)) {
            return true;
        }

        // Context authorizer Eloquent Model bekler; Authenticatable garanti değil.
        if (! $user instanceof Model) {
            return false;
        }

        try {
            $registry = app(ContextRegistry::class);
            $contextKey = $registry->keyForModel((string) $media->model_type);

            if ($contextKey === null) {
                return false;
            }

            $definition = $registry->get($contextKey);
            $owner = $definition->resolveOwner((string) $media->model_id);

            // Paylaşma/revoke mevcut bir dosya üzerinde yönetim işlemidir:
            // hiçbir şey yaratmaz ve hiçbir şey silmez → 'update'. Eski
            // toplu 'write' yeteneği kaldırıldı; context closure'ları artık
            // read/create/update/delete adlarından birini görür.
            return $definition->authorize($user, 'update', $owner);
        } catch (\Throwable) {
            // Owner kaydı silinmiş / context bozuk → 404 yerine güvenli reddet.
            return false;
        }
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

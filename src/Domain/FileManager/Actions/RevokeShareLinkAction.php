<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Lvntr\StarterKit\Domain\FileManager\Models\ShareRevocation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Signed share link'i revoke eder.
 *
 * Token hash + media_id çifti revocation tablosuna kaydedilir.
 * Sonraki her ziyarette ShareController@show bu hash'i lookup yapar
 * ve 410 Gone döner.
 *
 * İdempotent: aynı token hash ile tekrar çağrılırsa mevcut kayıt
 * döndürülür, yeni kayıt oluşturulmaz (firstOrCreate).
 */
class RevokeShareLinkAction extends FileManagerAction
{
    /**
     * @param  string  $tokenHash  Signature parametresinin SHA256 hash'i
     * @param  string|null  $revokedByUserId  İşlemi yapan kullanıcı ID'si (users.id UUID)
     */
    public function execute(
        Media $media,
        string $tokenHash,
        ?string $revokedByUserId = null,
        ?string $tenantId = null,
    ): ShareRevocation {
        // K2 (security): Composite (media_id, signed_token_hash) ile lookup.
        // Tekil signed_token_hash ile arama yapılsaydı, başka bir kullanıcı
        // kendi media_id'sini göndererek yabancı bir token'ı revoke edebilirdi.
        // Composite key ile token her zaman belirli bir medyaya kilitlidir.
        /** @var ShareRevocation $revocation */
        $revocation = ShareRevocation::firstOrCreate(
            [
                'media_id' => $media->getKey(),
                'signed_token_hash' => $tokenHash,
            ],
            [
                'tenant_id' => $tenantId,
                'revoked_at' => now(),
                'revoked_by_user_id' => $revokedByUserId,
            ],
        );

        return $revocation;
    }
}

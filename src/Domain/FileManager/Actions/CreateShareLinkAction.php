<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\URL;
use Lvntr\StarterKit\Domain\FileManager\DTOs\CreateShareLinkDTO;
use Lvntr\StarterKit\Domain\FileManager\DTOs\ShareLinkResultDTO;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Süreli imzalı paylaşım URL'si üretir.
 *
 * Ownership kontrolü zorunludur: media kaydının model_type + model_id
 * çifti, kimliği doğrulanmış kullanıcıya (ya da belirtilen owner'a)
 * ait olmalıdır. Bu kontrol olmadan herhangi bir media_id ile URL
 * üretmek bilgi sızıntısı yaratır.
 *
 * Token hash hesabı: Laravel signed URL'nin `signature` query parametresi
 * SHA256 ile hash'lenir ve yalnızca revoke edildiğinde DB'ye yazılır.
 * `URL::hasValidSignature()` imza doğrulamasını zaten yapar; burada
 * sadece revocation lookup'ı için hash kullanılır.
 */
class CreateShareLinkAction extends FileManagerAction
{
    public function execute(CreateShareLinkDTO $dto): ShareLinkResultDTO
    {
        // Ownership kontrolü: medya bu context'in sahibine mi ait?
        $this->assertOwnership($dto->media, $dto->ownerType, $dto->ownerId);

        $ttlHours = $dto->expiresInHours ?? (int) config('file-manager.share.default_ttl_hours', 24);
        $expiresAt = now()->addHours($ttlHours);

        $url = URL::temporarySignedRoute(
            'file-manager.share.show',
            $expiresAt,
            ['media' => $dto->media->getKey()],
        );

        // Signature parametresini URL'den çıkar ve hash'le.
        // Bu hash, revocation tablosunda lookup key olarak kullanılır.
        $tokenHash = $this->extractTokenHash($url);

        return new ShareLinkResultDTO(
            url: $url,
            expiresAt: $expiresAt,
            tokenHash: $tokenHash,
        );
    }

    /**
     * Medya kaydının verilen owner'a ait olduğunu doğrular.
     *
     * @throws AuthorizationException
     */
    private function assertOwnership(Media $media, string $ownerType, string $ownerId): void
    {
        if (
            $media->model_type !== $ownerType
            || (string) $media->model_id !== $ownerId
        ) {
            throw new AuthorizationException(
                __('sk-file-manager.errors.file_out_of_context')
            );
        }
    }

    /**
     * Signed URL'nin `signature` query parametresini bulur ve SHA256 hash'ler.
     */
    private function extractTokenHash(string $signedUrl): string
    {
        $parts = parse_url($signedUrl);
        parse_str($parts['query'] ?? '', $queryParams);

        $signature = (string) ($queryParams['signature'] ?? '');

        return hash('sha256', $signature);
    }
}

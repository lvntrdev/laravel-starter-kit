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
        // Collection guard: yalnız FileManager'ın `files` collection'ı
        // paylaşılabilir (DownloadFileAction'daki `files`-only sınırı ile
        // tutarlı). Guard olmadan `files` dışı medya (örn. avatar) için
        // public imzalı link üretilebilirdi.
        $this->assertShareableCollection($dto->media);

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

        // Audit sink (Task 8): üretilen paylaşım linki admin ActivityLog
        // UI'ında görünür. İmzalı URL ve signature/token hash KASITLI olarak
        // properties'e alınmaz — bunlar paylaşımın secret'ıdır. Kayıt yalnız
        // kimliği doğrulanmış aktör varken atılır (auto-resolved causer).
        if (auth()->check()) {
            activity('audit')
                ->performedOn($dto->media)
                ->event('created')
                ->withProperties([
                    'media_id' => $dto->media->getKey(),
                    'owner_type' => $dto->ownerType,
                    'owner_id' => $dto->ownerId,
                    'expires_at' => $expiresAt->toIso8601String(),
                ])
                ->log('Share link created');
        }

        return new ShareLinkResultDTO(
            url: $url,
            expiresAt: $expiresAt,
            tokenHash: $tokenHash,
        );
    }

    /**
     * Medyanın paylaşılabilir collection'da (`files`) olduğunu doğrular.
     *
     * FileManager yalnız `files` collection'ını yönetir ve paylaşır;
     * diğer collection'lar (avatar vb.) share mekanizmasının dışındadır.
     * ShareController@show aynı sınırı public endpoint tarafında 404 ile
     * uygular — iki taraf birlikte değişmelidir.
     *
     * @throws AuthorizationException
     */
    private function assertShareableCollection(Media $media): void
    {
        if ($media->collection_name !== 'files') {
            throw new AuthorizationException(
                __('sk-file-manager.errors.file_out_of_context')
            );
        }
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

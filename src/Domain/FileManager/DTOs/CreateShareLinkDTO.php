<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * CreateShareLinkAction'a geçirilen veri taşıyıcı.
 */
readonly class CreateShareLinkDTO extends BaseDTO
{
    public function __construct(
        /** Paylaşılacak medya kaydı */
        public Media $media,
        /** Ownership kontrolü için model_type (morph alias veya FQCN) */
        public string $ownerType,
        /** Ownership kontrolü için model_id */
        public string $ownerId,
        /** null ise config('file-manager.share.default_ttl_hours') kullanılır */
        public ?int $expiresInHours = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            media: $data['media'],
            ownerType: $data['owner_type'],
            ownerId: $data['owner_id'],
            expiresInHours: isset($data['expires_in_hours']) ? (int) $data['expires_in_hours'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'media_id' => $this->media->getKey(),
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'expires_in_hours' => $this->expiresInHours,
        ];
    }
}

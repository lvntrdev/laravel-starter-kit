<?php

namespace App\Domain\FileManager\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shape used by the FileManager frontend for a single file entry.
 */
readonly class FileItemDTO extends BaseDTO
{
    public function __construct(
        public int $id,
        public ?string $uuid,
        public string $name,
        public string $fileName,
        public string $mimeType,
        public int $size,
        public ?string $folderId,
        public string $url,
        public ?string $createdAt,
    ) {}

    public static function fromModel(Media $media): self
    {
        try {
            $url = $media->getTemporaryUrl(now()->addMinutes(30));
        } catch (RuntimeException) {
            $url = $media->getUrl();
        }

        return new self(
            id: (int) $media->id,
            uuid: $media->uuid,
            name: $media->name,
            fileName: $media->file_name,
            mimeType: (string) $media->mime_type,
            size: (int) $media->size,
            folderId: $media->folder_id,
            url: $url,
            createdAt: $media->created_at?->toIso8601String(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            uuid: $data['uuid'] ?? null,
            name: $data['name'],
            fileName: $data['file_name'],
            mimeType: $data['mime_type'],
            size: (int) $data['size'],
            folderId: $data['folder_id'] ?? null,
            url: $data['url'],
            createdAt: $data['created_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'folder_id' => $this->folderId,
            'url' => $this->url,
            'created_at' => $this->createdAt,
        ];
    }
}

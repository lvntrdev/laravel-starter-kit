<?php

namespace App\Domain\FileManager\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;
use App\Models\FileFolder;

readonly class FolderDTO extends BaseDTO
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public string $name,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}

    public static function fromModel(FileFolder $folder): self
    {
        return new self(
            id: (string) $folder->id,
            parentId: $folder->parent_id,
            name: $folder->name,
            ownerType: $folder->owner_type,
            ownerId: $folder->owner_id,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: (string) ($data['id'] ?? ''),
            parentId: $data['parent_id'] ?? null,
            name: $data['name'],
            ownerType: $data['owner_type'] ?? null,
            ownerId: $data['owner_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
        ];
    }
}

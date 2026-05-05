<?php

namespace Lvntr\StarterKit\Domain\FileManager\DTOs;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

readonly class FolderDTO extends BaseDTO
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public string $name,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}

    /**
     * Create a FolderDTO from a FileFolder Eloquent model instance.
     *
     * The parameter accepts `Model` instead of the concrete `FileFolder` class
     * so the vendor layer remains decoupled from the consumer app's model.
     * Pass any model that exposes `parent_id`, `name`, `owner_type`, `owner_id`.
     */
    public static function fromModel(Model $folder): self
    {
        return new self(
            id: (string) $folder->getKey(),
            parentId: $folder->getAttribute('parent_id'),
            name: $folder->getAttribute('name'),
            ownerType: $folder->getAttribute('owner_type'),
            ownerId: $folder->getAttribute('owner_id'),
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

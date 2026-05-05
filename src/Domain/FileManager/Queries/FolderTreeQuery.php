<?php

namespace Lvntr\StarterKit\Domain\FileManager\Queries;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;

/**
 * Returns the full nested folder tree for a given context, ordered by name.
 */
class FolderTreeQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(FileManagerContextDTO $context): array
    {
        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        $folders = $folderModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->orderBy('name')
            ->get();

        return $this->buildTree($folders, null);
    }

    /**
     * @param  Collection<int, Model>  $folders
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $folders, ?string $parentId): array
    {
        return $folders
            ->filter(fn (Model $folder) => $folder->getAttribute('parent_id') === $parentId)
            ->map(fn (Model $folder) => [
                'id' => (string) $folder->getKey(),
                'parent_id' => $folder->getAttribute('parent_id'),
                'name' => $folder->getAttribute('name'),
                'children' => $this->buildTree($folders, (string) $folder->getKey()),
            ])
            ->values()
            ->all();
    }
}

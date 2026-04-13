<?php

namespace App\Domain\FileManager\Queries;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Models\FileFolder;
use Illuminate\Support\Collection;

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
        $folders = FileFolder::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->orderBy('name')
            ->get();

        return $this->buildTree($folders, null);
    }

    /**
     * @param  Collection<int, FileFolder>  $folders
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $folders, ?string $parentId): array
    {
        return $folders
            ->filter(fn (FileFolder $folder) => $folder->parent_id === $parentId)
            ->map(fn (FileFolder $folder) => [
                'id' => (string) $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'children' => $this->buildTree($folders, (string) $folder->id),
            ])
            ->values()
            ->all();
    }
}

<?php

namespace App\Domain\FileManager\Queries;

use App\Domain\FileManager\DTOs\FileItemDTO;
use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Models\FileFavorite;
use App\Models\FileFolder;
use App\Models\Media;

/**
 * Returns all favorited folders and files for a given context owner.
 * Output shape is intentionally compatible with FolderContentsQuery.
 */
class FavoritesContentsQuery
{
    /**
     * @return array{
     *     folder: null,
     *     folders: array<int, array<string, mixed>>,
     *     files: array<int, array<string, mixed>>,
     *     stats: array{file_count: int, total_size: int},
     * }
     */
    public function execute(FileManagerContextDTO $context): array
    {
        $favorites = FileFavorite::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->get(['favoritable_type', 'favoritable_id']);

        $folderIds = $favorites
            ->where('favoritable_type', 'folder')
            ->pluck('favoritable_id')
            ->all();

        $fileIds = $favorites
            ->where('favoritable_type', 'file')
            ->pluck('favoritable_id')
            ->all();

        $folderModels = $folderIds === []
            ? collect()
            : FileFolder::query()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->whereIn('id', $folderIds)
                ->orderBy('name')
                ->get();

        $folders = $folderModels
            ->map(fn (FileFolder $folder) => [
                'id' => (string) $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'file_count' => 0, // favorites view does not aggregate subtree stats
                'total_size' => 0,
                'is_favorited' => true,
            ])
            ->values()
            ->all();

        $mediaModels = $fileIds === []
            ? collect()
            : Media::query()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->whereIn('id', $fileIds)
                ->orderBy('name')
                ->get();

        $files = $mediaModels
            ->map(function (Media $media) {
                $payload = FileItemDTO::fromModel($media)->toArray();
                $payload['is_favorited'] = true;

                return $payload;
            })
            ->values()
            ->all();

        return [
            'folder' => null,
            'folders' => $folders,
            'files' => $files,
            'stats' => [
                'file_count' => $mediaModels->count(),
                'total_size' => (int) $mediaModels->sum('size'),
            ],
        ];
    }
}

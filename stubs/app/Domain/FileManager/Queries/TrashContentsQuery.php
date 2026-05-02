<?php

namespace App\Domain\FileManager\Queries;

use App\Domain\FileManager\DTOs\FileItemDTO;
use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Models\FileFolder;
use App\Models\Media;

/**
 * Returns soft-deleted folders and files for a given context owner.
 * Output shape mirrors FolderContentsQuery so the frontend can reuse the
 * same grid component for the trash view.
 */
class TrashContentsQuery
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
        $folderModels = FileFolder::onlyTrashed()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->orderBy('deleted_at', 'desc')
            ->get();

        $folders = $folderModels
            ->map(fn (FileFolder $folder) => [
                'id' => (string) $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'file_count' => 0,
                'total_size' => 0,
                'is_favorited' => false,
                'deleted_at' => $folder->deleted_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $mediaModels = Media::onlyTrashed()
            ->where('model_type', $context->ownerType)
            ->where('model_id', $context->ownerId)
            ->where('collection_name', 'files')
            ->orderBy('deleted_at', 'desc')
            ->get();

        $files = $mediaModels
            ->map(function (Media $media) {
                $payload = FileItemDTO::fromModel($media)->toArray();
                $payload['is_favorited'] = false;
                $payload['deleted_at'] = $media->deleted_at?->toIso8601String();

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

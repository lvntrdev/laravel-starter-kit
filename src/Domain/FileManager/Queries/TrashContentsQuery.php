<?php

namespace Lvntr\StarterKit\Domain\FileManager\Queries;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\FileManager\Concerns\ResolvesMediaModel;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileItemDTO;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Returns soft-deleted folders and files for a given context owner.
 * Output shape mirrors FolderContentsQuery so the frontend can reuse the
 * same grid component for the trash view.
 */
class TrashContentsQuery
{
    use ResolvesMediaModel;

    /**
     * @return array{
     *     folder: null,
     *     folders: array<int, array<string, mixed>>,
     *     files: array<int, array<string, mixed>>,
     *     stats: array{file_count: int, total_size: int, storage_used: int, storage_quota: int},
     * }
     */
    public function execute(FileManagerContextDTO $context): array
    {
        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        $folderModels = $folderModel::onlyTrashed()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->orderBy('deleted_at', 'desc')
            ->get();

        $folders = $folderModels
            ->map(fn (Model $folder) => [
                'id' => (string) $folder->getKey(),
                'parent_id' => $folder->getAttribute('parent_id'),
                'name' => $folder->getAttribute('name'),
                'file_count' => 0,
                'total_size' => 0,
                'is_favorited' => false,
                'deleted_at' => $folder->getAttribute('deleted_at')?->toIso8601String(),
            ])
            ->values()
            ->all();

        $mediaModel = $this->mediaModel();
        $mediaModels = $mediaModel::onlyTrashed()
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
                'storage_used' => $this->computeStorageUsed(),
                'storage_quota' => $this->storageQuotaBytes(),
            ],
        ];
    }
}

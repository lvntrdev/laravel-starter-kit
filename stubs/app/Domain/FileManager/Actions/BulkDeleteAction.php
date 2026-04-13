<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\FileFolder;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Delete multiple folders and/or files in one transaction.
 *
 * @phpstan-type ItemInput array{type: 'folder'|'file', id: string}
 */
class BulkDeleteAction extends BaseAction
{
    /**
     * @param  array<int, array{type: string, id: string}>  $items
     * @return array{folders: int, files: int}
     */
    public function execute(FileManagerContextDTO $context, array $items): array
    {
        $folderIds = [];
        $fileIds = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'folder' && ! empty($item['id'])) {
                $folderIds[] = (string) $item['id'];
            } elseif (($item['type'] ?? null) === 'file' && ! empty($item['id'])) {
                $fileIds[] = (int) $item['id'];
            }
        }

        return DB::transaction(function () use ($context, $folderIds, $fileIds) {
            $deletedFiles = 0;
            $deletedFolders = 0;

            if ($fileIds !== []) {
                $files = Media::query()
                    ->where('model_type', $context->ownerType)
                    ->where('model_id', $context->ownerId)
                    ->where('collection_name', 'files')
                    ->whereIn('id', $fileIds)
                    ->get();

                foreach ($files as $media) {
                    $media->delete();
                    $deletedFiles++;
                }
            }

            if ($folderIds !== []) {
                $rootFolders = FileFolder::query()
                    ->where('owner_type', $context->ownerType)
                    ->where('owner_id', $context->ownerId)
                    ->whereIn('id', $folderIds)
                    ->get();

                foreach ($rootFolders as $folder) {
                    $descendants = $this->collectDescendantIds($folder);
                    $allIds = [...$descendants, (string) $folder->id];

                    $media = Media::query()
                        ->where('model_type', $context->ownerType)
                        ->where('model_id', $context->ownerId)
                        ->where('collection_name', 'files')
                        ->whereIn('folder_id', $allIds)
                        ->get();

                    foreach ($media as $m) {
                        $m->delete();
                        $deletedFiles++;
                    }

                    if ($descendants !== []) {
                        FileFolder::query()->whereIn('id', $descendants)->delete();
                    }

                    $folder->delete();
                    $deletedFolders++;
                }
            }

            return ['folders' => $deletedFolders, 'files' => $deletedFiles];
        });
    }

    /**
     * @return array<int, string>
     */
    private function collectDescendantIds(FileFolder $folder): array
    {
        $ids = [];
        $stack = [(string) $folder->id];

        while ($stack !== []) {
            $parentId = array_shift($stack);
            $children = FileFolder::query()->where('parent_id', $parentId)->pluck('id')->all();

            foreach ($children as $childId) {
                $ids[] = (string) $childId;
                $stack[] = (string) $childId;
            }
        }

        return $ids;
    }
}

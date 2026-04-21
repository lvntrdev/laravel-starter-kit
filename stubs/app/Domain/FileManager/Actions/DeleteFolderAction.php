<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\FileFolder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Cascade-delete a folder: its subfolders (DB cascade) AND every Media record
 * contained anywhere beneath it (Media is removed via Spatie so files on disk
 * are cleaned up too).
 */
class DeleteFolderAction extends BaseAction
{
    public function execute(FileManagerContextDTO $context, FileFolder $folder): void
    {
        if ($folder->owner_type !== $context->ownerType || (string) $folder->owner_id !== $context->ownerId) {
            throw new LogicException(__('sk-file-manager.errors.folder_out_of_context'));
        }

        DB::transaction(function () use ($context, $folder) {
            $descendantIds = $this->collectDescendantIds($context, $folder);
            $folderIds = [...$descendantIds, (string) $folder->id];

            Media::query()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->whereIn('folder_id', $folderIds)
                ->get()
                ->each(fn (Media $media) => $media->delete());

            if ($descendantIds !== []) {
                FileFolder::query()->whereIn('id', $descendantIds)->delete();
            }

            $folder->delete();
        });
    }

    /**
     * Walk the folder subtree in PHP using a single pre-loaded parent_id map,
     * so depth does not translate into an extra query per level.
     *
     * @return array<int, string>
     */
    private function collectDescendantIds(FileManagerContextDTO $context, FileFolder $folder): array
    {
        $rows = FileFolder::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->get(['id', 'parent_id']);

        $childrenByParent = [];
        foreach ($rows as $row) {
            $parentId = $row->parent_id === null ? '' : (string) $row->parent_id;
            $childrenByParent[$parentId][] = (string) $row->id;
        }

        $ids = [];
        $stack = [(string) $folder->id];

        while ($stack !== []) {
            $parentId = array_shift($stack);
            foreach ($childrenByParent[$parentId] ?? [] as $childId) {
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }
}

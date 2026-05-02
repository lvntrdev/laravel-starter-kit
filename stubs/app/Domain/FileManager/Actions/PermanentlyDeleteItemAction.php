<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\FileFolder;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Permanently remove a trashed folder or file. For folders this cascades
 * through descendants (sub-folders and contained media), forceDeleting each
 * model so Spatie's MediaObserver removes the physical file from disk and the
 * cascade-clean listener wipes any orphaned favorites.
 */
class PermanentlyDeleteItemAction extends BaseAction
{
    public function execute(FileManagerContextDTO $context, string $itemType, string $itemId): void
    {
        if (! in_array($itemType, ['folder', 'file'], true)) {
            throw new LogicException(__('sk-file-manager.errors.invalid_trash_item_type'));
        }

        match ($itemType) {
            'folder' => $this->forceDeleteFolder($context, $itemId),
            'file' => $this->forceDeleteFile($context, $itemId),
        };
    }

    private function forceDeleteFile(FileManagerContextDTO $context, string $fileId): void
    {
        /** @var Media|null $media */
        $media = Media::withTrashed()
            ->where('model_type', $context->ownerType)
            ->where('model_id', $context->ownerId)
            ->where('collection_name', 'files')
            ->where('id', $fileId)
            ->first();

        if ($media === null) {
            throw new LogicException(__('sk-file-manager.errors.trash_item_not_found'));
        }

        // Spatie's MediaObserver removes the physical file when isForceDeleting() is true.
        $media->forceDelete();
    }

    private function forceDeleteFolder(FileManagerContextDTO $context, string $folderId): void
    {
        /** @var FileFolder|null $folder */
        $folder = FileFolder::withTrashed()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('id', $folderId)
            ->first();

        if ($folder === null) {
            throw new LogicException(__('sk-file-manager.errors.trash_item_not_found'));
        }

        DB::transaction(function () use ($context, $folder) {
            $descendantIds = $this->collectDescendantIds($context, $folder);
            $allFolderIds = [...$descendantIds, (string) $folder->id];

            // Force-delete every media row beneath the folder. Iterate per-model
            // (rather than a bulk delete query) so Spatie's observer fires and
            // removes the physical file from disk.
            Media::withTrashed()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->whereIn('folder_id', $allFolderIds)
                ->get()
                ->each(fn (Media $media) => $media->forceDelete());

            // Force-delete descendant folders one by one so the favorites
            // cascade-clean listener fires for each.
            if ($descendantIds !== []) {
                FileFolder::withTrashed()
                    ->whereIn('id', $descendantIds)
                    ->get()
                    ->each(fn (FileFolder $f) => $f->forceDelete());
            }

            $folder->forceDelete();
        });
    }

    /**
     * Walk the folder subtree using a single in-memory parent_id map.
     * Includes both trashed and non-trashed folders so the cascade reaches
     * descendants that were already in trash before this call.
     *
     * @return array<int, string>
     */
    private function collectDescendantIds(FileManagerContextDTO $context, FileFolder $folder): array
    {
        $rows = FileFolder::withTrashed()
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

<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Permanently remove a trashed folder or file. For folders this cascades
 * through descendants (sub-folders and contained media), forceDeleting each
 * model so Spatie's MediaObserver removes the physical file from disk and the
 * cascade-clean listener wipes any orphaned favorites.
 */
class PermanentlyDeleteItemAction extends FileManagerAction
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
        $media = $this->mediaModel()::withTrashed()
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
        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        /** @var Model|null $folder */
        $folder = $folderModel::withTrashed()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('id', $folderId)
            ->first();

        if ($folder === null) {
            throw new LogicException(__('sk-file-manager.errors.trash_item_not_found'));
        }

        DB::transaction(function () use ($context, $folder, $folderModel) {
            $descendantIds = $this->collectDescendantIds($context, $folder, $folderModel);
            $allFolderIds = [...$descendantIds, (string) $folder->getKey()];

            // Force-delete every media row beneath the folder. Iterate per-model
            // (rather than a bulk delete query) so Spatie's observer fires and
            // removes the physical file from disk.
            $this->mediaModel()::withTrashed()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->whereIn('folder_id', $allFolderIds)
                ->get()
                ->each(fn (Media $media) => $media->forceDelete());

            // Force-delete descendant folders one by one so the favorites
            // cascade-clean listener fires for each.
            if ($descendantIds !== []) {
                $folderModel::withTrashed()
                    ->whereIn('id', $descendantIds)
                    ->get()
                    ->each(fn (Model $f) => $f->forceDelete());
            }

            $folder->forceDelete();
        });
    }

    /**
     * Walk the folder subtree using a single in-memory parent_id map.
     * Includes both trashed and non-trashed folders so the cascade reaches
     * descendants that were already in trash before this call.
     *
     * @param  class-string<Model>  $folderModel
     * @return array<int, string>
     */
    private function collectDescendantIds(FileManagerContextDTO $context, Model $folder, string $folderModel): array
    {
        $rows = $folderModel::withTrashed()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->get(['id', 'parent_id']);

        $childrenByParent = [];
        foreach ($rows as $row) {
            $parentId = $row->parent_id === null ? '' : (string) $row->parent_id;
            $childrenByParent[$parentId][] = (string) $row->id;
        }

        $ids = [];
        $stack = [(string) $folder->getKey()];

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

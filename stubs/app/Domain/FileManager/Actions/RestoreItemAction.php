<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\FileFolder;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Restore a soft-deleted folder or file from trash back into the active tree.
 *
 * Refuses to restore an item whose parent folder is still trashed — reviving
 * an orphan would surface a confusing dangling row in the user's listing.
 *
 * When restoring a folder, all descendant folders and their contained files
 * (collection_name = 'files') are cascade-restored in a single transaction,
 * mirroring the cascade soft-delete performed by DeleteFolderAction.
 */
class RestoreItemAction extends BaseAction
{
    public function execute(FileManagerContextDTO $context, string $itemType, string $itemId): void
    {
        if (! in_array($itemType, ['folder', 'file'], true)) {
            throw new LogicException(__('sk-file-manager.errors.invalid_trash_item_type'));
        }

        match ($itemType) {
            'folder' => $this->restoreFolder($context, $itemId),
            'file' => $this->restoreFile($context, $itemId),
        };
    }

    private function restoreFolder(FileManagerContextDTO $context, string $folderId): void
    {
        /** @var FileFolder|null $folder */
        $folder = FileFolder::onlyTrashed()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('id', $folderId)
            ->first();

        if ($folder === null) {
            throw new LogicException(__('sk-file-manager.errors.trash_item_not_found'));
        }

        if ($folder->parent_id !== null) {
            /** @var FileFolder|null $parent */
            $parent = FileFolder::withTrashed()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->where('id', $folder->parent_id)
                ->first();

            if ($parent === null) {
                // Parent permanently deleted — move to root to avoid orphan parent_id.
                $folder->parent_id = null;
            } elseif ($parent->trashed()) {
                throw new LogicException(__('sk-file-manager.errors.restore_parent_trashed'));
            }
        }

        DB::transaction(function () use ($context, $folder) {
            $this->cascadeRestore($context, $folder);
        });
    }

    private function restoreFile(FileManagerContextDTO $context, string $fileId): void
    {
        /** @var Media|null $media */
        $media = Media::onlyTrashed()
            ->where('model_type', $context->ownerType)
            ->where('model_id', $context->ownerId)
            ->where('collection_name', 'files')
            ->where('id', $fileId)
            ->first();

        if ($media === null) {
            throw new LogicException(__('sk-file-manager.errors.trash_item_not_found'));
        }

        if ($media->folder_id !== null) {
            /** @var FileFolder|null $parent */
            $parent = FileFolder::withTrashed()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->where('id', $media->folder_id)
                ->first();

            if ($parent === null) {
                // Parent folder permanently deleted — move file to root to avoid
                // orphan folder_id reference (silent data loss otherwise).
                $media->folder_id = null;
            } elseif ($parent->trashed()) {
                throw new LogicException(__('sk-file-manager.errors.restore_parent_trashed'));
            }
        }

        $media->restore();
    }

    /**
     * Restore the given folder, all its descendant folders, and every file
     * (collection_name = 'files') contained anywhere in that subtree.
     *
     * Mirrors DeleteFolderAction: collect IDs from a single pre-loaded map so
     * depth does not cause an extra query per level (no N+1).
     */
    private function cascadeRestore(FileManagerContextDTO $context, FileFolder $folder): void
    {
        $descendantIds = $this->collectTrashedDescendantIds($context, $folder);
        $folderIds = [...$descendantIds, (string) $folder->id];

        // Restore all folders in the subtree (parent first, children after —
        // order does not matter for soft-delete restore but is cleaner).
        $folder->restore();

        if ($descendantIds !== []) {
            FileFolder::onlyTrashed()->whereIn('id', $descendantIds)->restore();
        }

        // Restore every 'files'-collection Media record belonging to this subtree.
        // $folderIds already includes $folder->id so files placed directly in the
        // root folder (no sub-folder) are also covered.
        Media::onlyTrashed()
            ->where('model_type', $context->ownerType)
            ->where('model_id', $context->ownerId)
            ->where('collection_name', 'files')
            ->whereIn('folder_id', $folderIds)
            ->restore();
    }

    /**
     * Walk the trashed folder subtree using a single pre-loaded parent_id map,
     * mirroring DeleteFolderAction::collectDescendantIds but scoped to trashed
     * rows so only soft-deleted descendants are returned.
     *
     * @return array<int, string>
     */
    private function collectTrashedDescendantIds(FileManagerContextDTO $context, FileFolder $folder): array
    {
        // Load ALL trashed folders for this owner in one query to avoid per-level
        // queries when walking deep hierarchies.
        $rows = FileFolder::onlyTrashed()
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

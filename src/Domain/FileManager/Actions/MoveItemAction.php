<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Exceptions\DomainRuleException;

/**
 * Move a folder or a file to another (or root) folder within the same context.
 *
 * All moves are metadata-only — physical paths are derived from owner+mediaUuid
 * by MediaPathGenerator, so `folder_id` updates suffice.
 */
class MoveItemAction extends FileManagerAction
{
    public function execute(
        FileManagerContextDTO $context,
        string $itemType,
        string $itemId,
        ?string $targetFolderId,
    ): void {
        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        $this->assertTargetValid($context, $targetFolderId, $folderModel);

        match ($itemType) {
            'folder' => $this->moveFolder($context, $itemId, $targetFolderId, $folderModel),
            'file' => $this->moveFile($context, $itemId, $targetFolderId, $folderModel),
            default => throw new DomainRuleException("Unsupported item type: {$itemType}"),
        };
    }

    /** @param  class-string<Model>  $folderModel */
    private function assertTargetValid(FileManagerContextDTO $context, ?string $targetFolderId, string $folderModel): void
    {
        if ($targetFolderId === null) {
            return;
        }

        $exists = $folderModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('id', $targetFolderId)
            ->exists();

        if (! $exists) {
            throw new DomainRuleException(__('sk-file-manager.errors.target_missing'));
        }
    }

    /** @param  class-string<Model>  $folderModel */
    private function moveFolder(FileManagerContextDTO $context, string $folderId, ?string $targetFolderId, string $folderModel): void
    {
        $folder = $folderModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('id', $folderId)
            ->firstOrFail();

        if ($targetFolderId !== null && $this->wouldCreateCycle($context, $folder, $targetFolderId, $folderModel)) {
            throw new DomainRuleException(__('sk-file-manager.errors.move_cycle'));
        }

        // Pre-check handles parent_id=NULL where the unique index does not
        // enforce uniqueness. The catch guards the narrow race window.
        $duplicate = $folderModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('parent_id', $targetFolderId)
            ->where('name', $folder->getAttribute('name'))
            ->where('id', '!=', $folder->getKey())
            ->exists();

        if ($duplicate) {
            throw new DomainRuleException(__('sk-file-manager.errors.duplicate_folder'));
        }

        try {
            $folder->update(['parent_id' => $targetFolderId]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new DomainRuleException(__('sk-file-manager.errors.duplicate_folder'));
            }

            throw $e;
        }
    }

    /** @param  class-string<Model>  $folderModel */
    private function moveFile(FileManagerContextDTO $context, string $mediaId, ?string $targetFolderId, string $folderModel): void
    {
        $media = $this->mediaModel()::query()
            ->where('model_type', $context->ownerType)
            ->where('model_id', $context->ownerId)
            ->where('collection_name', 'files')
            ->where('id', (int) $mediaId)
            ->firstOrFail();

        $media->folder_id = $targetFolderId;
        $media->save();
    }

    /**
     * Walk up from `targetFolderId` toward root using a single pre-loaded
     * id → parent_id map, avoiding one SELECT per ancestor.
     *
     * @param  class-string<Model>  $folderModel
     */
    private function wouldCreateCycle(FileManagerContextDTO $context, Model $folder, string $targetFolderId, string $folderModel): bool
    {
        /** @var Collection<string, string|null> $parents */
        $parents = $folderModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->pluck('parent_id', 'id');

        $currentId = $targetFolderId;
        $visited = [];

        while ($currentId !== null) {
            if ((string) $currentId === (string) $folder->getKey()) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return true;
            }
            $visited[$currentId] = true;

            $currentId = $parents[$currentId] ?? null;
        }

        return false;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}

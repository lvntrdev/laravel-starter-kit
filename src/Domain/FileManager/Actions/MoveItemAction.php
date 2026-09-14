<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /**
     * The cycle check and the parent write must observe the same state, so both
     * run inside one transaction behind a row lock on the folders involved —
     * the same `lockForUpdate` idiom UploadFileAction::ensureManagedFolder uses.
     *
     * @param  class-string<Model>  $folderModel
     */
    private function moveFolder(FileManagerContextDTO $context, string $folderId, ?string $targetFolderId, string $folderModel): void
    {
        DB::transaction(function () use ($context, $folderId, $targetFolderId, $folderModel): void {
            // Source and target are locked by one ordered statement: a fixed
            // acquisition order is what turns two mutual moves into a queue
            // instead of a deadlock. The ancestor map is read AFTER the lock so
            // it observes whatever the competing move committed before it let
            // the row go.
            //
            // ponytail: the lock deliberately covers only the source and the
            // target row (a context-wide lock would serialise every move). That
            // makes the check atomic for this pair, but two moves over disjoint
            // pairs can still weave a cycle between them — moving A under B
            // while C moves under D, where B already descends from C and D from
            // A. Upgrade path: lock the target's whole ancestor chain, or carry
            // a closure table and let the database enforce acyclicity. Until
            // then FolderContentsQuery::collectSubtreeIds is the backstop — it
            // terminates on a cyclic graph instead of walking it forever.
            $locked = $folderModel::query()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->whereIn('id', array_values(array_filter([$folderId, $targetFolderId])))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Model $row): string => (string) $row->getKey());

            $folder = $locked->get($folderId);

            if (! $folder instanceof Model) {
                throw (new ModelNotFoundException)->setModel($folderModel, [$folderId]);
            }

            if ($targetFolderId !== null) {
                // Re-checked under the lock: the target may have been deleted
                // between execute()'s existence probe and this transaction.
                if (! $locked->has($targetFolderId)) {
                    throw new DomainRuleException(__('sk-file-manager.errors.target_missing'));
                }

                if ($this->wouldCreateCycle($context, $folder, $targetFolderId, $folderModel)) {
                    throw new DomainRuleException(__('sk-file-manager.errors.move_cycle'));
                }
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
        });
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

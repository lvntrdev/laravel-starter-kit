<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use LogicException;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;

class RenameFolderAction extends FileManagerAction
{
    public function execute(FileManagerContextDTO $context, Model $folder, string $name): Model
    {
        $this->assertBelongsToContext($context, $folder);

        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        // Pre-check handles parent_id=NULL where the unique index does not
        // enforce uniqueness (SQLite/MySQL treat NULL as distinct).
        $exists = $folderModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('parent_id', $folder->getAttribute('parent_id'))
            ->where('name', $name)
            ->where('id', '!=', $folder->getKey())
            ->exists();

        if ($exists) {
            throw new LogicException(__('sk-file-manager.errors.duplicate_folder'));
        }

        try {
            $folder->update(['name' => $name]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new LogicException(__('sk-file-manager.errors.duplicate_folder'));
            }

            throw $e;
        }

        return $folder->refresh();
    }

    private function assertBelongsToContext(FileManagerContextDTO $context, Model $folder): void
    {
        if ($folder->getAttribute('owner_type') !== $context->ownerType || (string) $folder->getAttribute('owner_id') !== $context->ownerId) {
            throw new LogicException(__('sk-file-manager.errors.folder_out_of_context'));
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}

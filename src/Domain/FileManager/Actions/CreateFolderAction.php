<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;

class CreateFolderAction extends FileManagerAction
{
    public function execute(FileManagerContextDTO $context, string $name, ?string $parentId = null): Model
    {
        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        if ($parentId !== null) {
            $folderModel::query()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->where('id', $parentId)
                ->firstOrFail();
        }

        // Pre-check catches the common duplicate case and also handles the
        // parent_id=NULL case where the (owner, parent_id, name) unique
        // index does not prevent duplicates on engines that treat NULL as
        // distinct (MySQL, SQLite).
        $exists = $folderModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            throw new LogicException(__('sk-file-manager.errors.duplicate_folder'));
        }

        // Race window: two concurrent requests can both pass the exists check.
        // The unique index closes that window for non-null parent_id; we catch
        // the violation and translate it to a clean LogicException.
        try {
            return $folderModel::create([
                'parent_id' => $parentId,
                'name' => $name,
                'owner_type' => $context->ownerType,
                'owner_id' => $context->ownerId,
                'created_by' => Auth::id(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new LogicException(__('sk-file-manager.errors.duplicate_folder'));
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}

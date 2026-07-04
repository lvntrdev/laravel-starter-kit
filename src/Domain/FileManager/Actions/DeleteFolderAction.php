<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Exceptions\DomainRuleException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Cascade-delete a folder: its subfolders (DB cascade) AND every Media record
 * contained anywhere beneath it (Media is removed via Spatie so files on disk
 * are cleaned up too).
 */
class DeleteFolderAction extends FileManagerAction
{
    public function execute(FileManagerContextDTO $context, Model $folder): void
    {
        if ($folder->getAttribute('owner_type') !== $context->ownerType || (string) $folder->getAttribute('owner_id') !== $context->ownerId) {
            throw new DomainRuleException(__('sk-file-manager.errors.folder_out_of_context'));
        }

        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        $enableTrash = (bool) config('file-manager.settings.enable_trash', true);

        DB::transaction(function () use ($context, $folder, $folderModel, $enableTrash) {
            $descendantIds = $this->collectDescendantIds($context, $folder, $folderModel);
            $folderIds = [...$descendantIds, (string) $folder->getKey()];

            $this->mediaModel()::query()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->whereIn('folder_id', $folderIds)
                ->get()
                ->each(fn (Media $media) => $enableTrash ? $media->delete() : $media->forceDelete());

            if ($descendantIds !== []) {
                $enableTrash
                    ? $folderModel::query()->whereIn('id', $descendantIds)->delete()
                    : $folderModel::query()->whereIn('id', $descendantIds)->get()->each->forceDelete();
            }

            $enableTrash ? $folder->delete() : $folder->forceDelete();
        });
    }

    /**
     * Walk the folder subtree in PHP using a single pre-loaded parent_id map,
     * so depth does not translate into an extra query per level.
     *
     * @param  class-string<Model>  $folderModel
     * @return array<int, string>
     */
    private function collectDescendantIds(FileManagerContextDTO $context, Model $folder, string $folderModel): array
    {
        $rows = $folderModel::query()
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

<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Permanently purge every soft-deleted folder and file for the given context.
 *
 * Media is force-deleted first so Spatie's MediaObserver removes physical files
 * from storage. Folders are then force-deleted in post-order (children before
 * parents) so each model's forceDeleted observer fires and cleans up FileFavorite
 * rows without relying on DB-level cascade ordering.
 *
 * @phpstan-type FolderMap array<string, list<string>>
 */
class EmptyTrashAction extends FileManagerAction
{
    /**
     * @return array{folders: int, files: int}
     */
    public function execute(FileManagerContextDTO $context): array
    {
        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        return DB::transaction(function () use ($context, $folderModel) {
            $fileCount = 0;

            $this->mediaModel()::onlyTrashed()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->get()
                ->each(function (Media $media) use (&$fileCount) {
                    $media->forceDelete();
                    $fileCount++;
                });

            /** @var Collection<int, Model> $folders */
            $folders = $folderModel::onlyTrashed()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->get();

            $folderCount = $folders->count();

            if ($folderCount > 0) {
                /** @var array<string, true> $trashedIds */
                $trashedIds = $folders->pluck('id')->mapWithKeys(fn ($id) => [(string) $id => true])->all();

                /** @var FolderMap $childrenOf */
                $childrenOf = [];
                $roots = [];

                foreach ($folders as $folder) {
                    $id = (string) $folder->getKey();
                    $parentId = $folder->getAttribute('parent_id') ? (string) $folder->getAttribute('parent_id') : null;

                    if ($parentId === null || ! isset($trashedIds[$parentId])) {
                        $roots[] = $id;
                    } else {
                        $childrenOf[$parentId][] = $id;
                    }
                }

                /** @var array<string, Model> $modelMap */
                $modelMap = $folders->keyBy(fn (Model $f) => (string) $f->getKey())->all();

                $deleteOrder = [];
                foreach ($roots as $rootId) {
                    $this->walkPostOrder($rootId, $childrenOf, $deleteOrder);
                }

                foreach ($deleteOrder as $folderId) {
                    ($modelMap[$folderId] ?? null)?->forceDelete();
                }
            }

            return ['folders' => $folderCount, 'files' => $fileCount];
        });
    }

    /**
     * Post-order DFS: children are appended before their parent.
     *
     * @param  FolderMap  $childrenOf
     * @param  list<string>  $order
     */
    private function walkPostOrder(string $id, array $childrenOf, array &$order): void
    {
        foreach ($childrenOf[$id] ?? [] as $childId) {
            $this->walkPostOrder($childId, $childrenOf, $order);
        }
        $order[] = $id;
    }
}

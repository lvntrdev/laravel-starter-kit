<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\FileFolder;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
class EmptyTrashAction extends BaseAction
{
    /**
     * @return array{folders: int, files: int}
     */
    public function execute(FileManagerContextDTO $context): array
    {
        return DB::transaction(function () use ($context) {
            $fileCount = 0;

            Media::onlyTrashed()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->get()
                ->each(function (Media $media) use (&$fileCount) {
                    $media->forceDelete();
                    $fileCount++;
                });

            /** @var Collection<int, FileFolder> $folders */
            $folders = FileFolder::onlyTrashed()
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
                    $id = (string) $folder->id;
                    $parentId = $folder->parent_id ? (string) $folder->parent_id : null;

                    if ($parentId === null || ! isset($trashedIds[$parentId])) {
                        $roots[] = $id;
                    } else {
                        $childrenOf[$parentId][] = $id;
                    }
                }

                /** @var array<string, FileFolder> $modelMap */
                $modelMap = $folders->keyBy(fn (FileFolder $f) => (string) $f->id)->all();

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

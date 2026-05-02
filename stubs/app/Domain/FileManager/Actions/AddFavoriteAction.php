<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Exceptions\ApiException;
use App\Models\FileFavorite;
use App\Models\FileFolder;
use App\Models\Media;
use LogicException;

class AddFavoriteAction extends BaseAction
{
    /**
     * Add a folder or file to the owner's favorites (idempotent).
     *
     * @throws LogicException when the type is invalid or the item does not exist in context
     */
    public function execute(
        FileManagerContextDTO $context,
        string $favoritableType,
        string $favoritableId,
    ): FileFavorite {
        if (! in_array($favoritableType, ['folder', 'file'], true)) {
            throw new LogicException(__('sk-file-manager.errors.invalid_favoritable_type'));
        }

        if ($favoritableType === 'file') {
            // Throw 404 if the Media record exists but is not in the 'files' collection
            // (e.g. avatar, editor), keeping collection scope intact.
            $media = Media::query()->find($favoritableId);

            if ($media !== null && $media->collection_name !== 'files') {
                throw ApiException::notFound();
            }
        }

        $exists = match ($favoritableType) {
            'folder' => FileFolder::query()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->where('id', $favoritableId)
                ->exists(),
            'file' => Media::query()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->where('id', $favoritableId)
                ->exists(),
        };

        if (! $exists) {
            throw new LogicException(__('sk-file-manager.errors.favoritable_not_found'));
        }

        return FileFavorite::query()->firstOrCreate([
            'owner_type' => $context->ownerType,
            'owner_id' => $context->ownerId,
            'favoritable_type' => $favoritableType,
            'favoritable_id' => $favoritableId,
        ]);
    }
}

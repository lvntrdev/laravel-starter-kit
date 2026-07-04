<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Exceptions\ApiException;
use Lvntr\StarterKit\Exceptions\DomainRuleException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AddFavoriteAction extends FileManagerAction
{
    /**
     * Add a folder or file to the owner's favorites (idempotent).
     *
     * @throws DomainRuleException when the type is invalid or the item does not exist in context
     */
    public function execute(
        FileManagerContextDTO $context,
        string $favoritableType,
        string $favoritableId,
    ): Model {
        if (! in_array($favoritableType, ['folder', 'file'], true)) {
            throw new DomainRuleException(__('sk-file-manager.errors.invalid_favoritable_type'));
        }

        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        /** @var class-string<Model> $favoriteModel */
        $favoriteModel = config('file-manager.models.favorite', 'App\\Models\\FileFavorite');

        if ($favoritableType === 'file') {
            // Throw 404 if the Media record exists but is not in the 'files' collection
            // (e.g. avatar, editor), keeping collection scope intact.
            $media = $this->mediaModel()::query()->find($favoritableId);

            if ($media !== null && $media->collection_name !== 'files') {
                throw ApiException::notFound();
            }
        }

        $exists = match ($favoritableType) {
            'folder' => $folderModel::query()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->where('id', $favoritableId)
                ->exists(),
            'file' => $this->mediaModel()::query()
                ->where('model_type', $context->ownerType)
                ->where('model_id', $context->ownerId)
                ->where('collection_name', 'files')
                ->where('id', $favoritableId)
                ->exists(),
        };

        if (! $exists) {
            throw new DomainRuleException(__('sk-file-manager.errors.favoritable_not_found'));
        }

        return $favoriteModel::query()->firstOrCreate([
            'owner_type' => $context->ownerType,
            'owner_id' => $context->ownerId,
            'favoritable_type' => $favoritableType,
            'favoritable_id' => $favoritableId,
        ]);
    }
}

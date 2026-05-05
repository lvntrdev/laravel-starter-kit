<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;

class RemoveFavoriteAction extends FileManagerAction
{
    /**
     * Remove a folder or file from the owner's favorites (idempotent — no-op if not favorited).
     */
    public function execute(
        FileManagerContextDTO $context,
        string $favoritableType,
        string $favoritableId,
    ): void {
        /** @var class-string<Model> $favoriteModel */
        $favoriteModel = config('file-manager.models.favorite', 'App\\Models\\FileFavorite');

        $favoriteModel::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('favoritable_type', $favoritableType)
            ->where('favoritable_id', $favoritableId)
            ->delete();
    }
}

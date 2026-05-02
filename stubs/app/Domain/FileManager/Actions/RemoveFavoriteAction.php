<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\FileFavorite;

class RemoveFavoriteAction extends BaseAction
{
    /**
     * Remove a folder or file from the owner's favorites (idempotent — no-op if not favorited).
     */
    public function execute(
        FileManagerContextDTO $context,
        string $favoritableType,
        string $favoritableId,
    ): void {
        FileFavorite::query()
            ->where('owner_type', $context->ownerType)
            ->where('owner_id', $context->ownerId)
            ->where('favoritable_type', $favoritableType)
            ->where('favoritable_id', $favoritableId)
            ->delete();
    }
}

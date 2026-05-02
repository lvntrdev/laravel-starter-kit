<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Exceptions\ApiException;
use App\Models\Media;
use LogicException;

class RenameFileAction extends BaseAction
{
    public function execute(FileManagerContextDTO $context, Media $media, string $newName): Media
    {
        if ($media->collection_name !== 'files') {
            throw ApiException::notFound();
        }

        if (
            $media->model_type !== $context->ownerType ||
            (string) $media->model_id !== $context->ownerId
        ) {
            throw new LogicException(__('sk-file-manager.errors.file_out_of_context'));
        }

        $exists = Media::query()
            ->where('model_type', $context->ownerType)
            ->where('model_id', $context->ownerId)
            ->where('folder_id', $media->folder_id)
            ->where('file_name', $newName)
            ->where('id', '!=', $media->id)
            ->exists();

        if ($exists) {
            throw new LogicException(__('sk-file-manager.errors.duplicate_file'));
        }

        $media->file_name = $newName;
        $media->name = pathinfo($newName, PATHINFO_FILENAME);
        $media->save();

        return $media->refresh();
    }
}

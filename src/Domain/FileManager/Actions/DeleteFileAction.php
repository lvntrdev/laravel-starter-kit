<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use LogicException;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DeleteFileAction extends FileManagerAction
{
    public function execute(FileManagerContextDTO $context, Media $media): void
    {
        if (
            $media->collection_name !== 'files'
            || $media->model_type !== $context->ownerType
            || (string) $media->model_id !== $context->ownerId
        ) {
            throw new LogicException(__('sk-file-manager.errors.file_out_of_context'));
        }

        $media->delete();
    }
}

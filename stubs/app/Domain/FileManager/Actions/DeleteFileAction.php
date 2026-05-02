<?php

namespace App\Domain\FileManager\Actions;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\Media;
use LogicException;

class DeleteFileAction extends BaseAction
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

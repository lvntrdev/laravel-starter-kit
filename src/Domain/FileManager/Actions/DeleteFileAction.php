<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Exceptions\DomainRuleException;
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
            throw new DomainRuleException(__('sk-file-manager.errors.file_out_of_context'));
        }

        config('file-manager.settings.enable_trash', true)
            ? $media->delete()
            : $media->forceDelete();
    }
}

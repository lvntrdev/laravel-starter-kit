<?php

namespace Lvntr\StarterKit\Domain\FileManager\Actions;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Exceptions\ApiException;
use Lvntr\StarterKit\Exceptions\DomainRuleException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class CopyFileAction extends FileManagerAction
{
    public function execute(
        FileManagerContextDTO $context,
        Media $media,
        ?string $targetFolderId = null,
    ): Media {
        // 1. Collection scope guard — only 'files' collection is permitted
        if ($media->collection_name !== 'files') {
            throw ApiException::notFound();
        }

        // 2. Source file context check
        if (
            $media->model_type !== $context->ownerType ||
            (string) $media->model_id !== $context->ownerId
        ) {
            throw new DomainRuleException(__('sk-file-manager.errors.file_out_of_context'));
        }

        /** @var class-string<Model> $folderModel */
        $folderModel = config('file-manager.models.folder', 'App\\Models\\FileFolder');

        // 3. Target folder resolve (default: source's folder)
        $resolvedFolderId = $targetFolderId ?? $media->folder_id;

        // 4. Target folder context check (if not root)
        if ($resolvedFolderId !== null) {
            $exists = $folderModel::query()
                ->where('owner_type', $context->ownerType)
                ->where('owner_id', $context->ownerId)
                ->where('id', $resolvedFolderId)
                ->exists();

            if (! $exists) {
                throw new DomainRuleException(__('sk-file-manager.errors.target_missing'));
            }
        }

        // 4. Naming: "{base} (copy).ext" → "{base} (copy 2).ext" → ...
        $newName = $this->generateCopyName(
            originalFileName: $media->file_name,
            ownerType: $context->ownerType,
            ownerId: $context->ownerId,
            folderId: $resolvedFolderId,
        );

        // 5. Spatie copy: new Media row + new physical file (UUID-based)
        // Not transactional — Spatie does I/O. Manual cleanup on failure.
        $copy = null;

        try {
            /** @var Media $copy */
            $copy = $media->copy($context->owner, 'files', $media->disk);

            $copy->folder_id = $resolvedFolderId;
            $copy->file_name = $newName;
            $copy->name = pathinfo($newName, PATHINFO_FILENAME);
            $copy->save();

            return $copy->refresh();
        } catch (Throwable $e) {
            // Cleanup: orphan Media row + physical file from Spaces/disk
            if ($copy !== null && $copy->exists) {
                $copy->forceDelete();
            }
            throw new DomainRuleException(__('sk-file-manager.errors.copy_failed'));
        }
    }

    /**
     * Find first available copy-suffixed name in target folder.
     * "photo.jpg" → "photo (copy).jpg" → "photo (copy 2).jpg" → ...
     */
    private function generateCopyName(
        string $originalFileName,
        string $ownerType,
        string $ownerId,
        ?string $folderId,
    ): string {
        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
        $base = pathinfo($originalFileName, PATHINFO_FILENAME);
        $extPart = $extension !== '' ? ".{$extension}" : '';

        // Strip existing "(copy)" or "(copy N)" suffix from base if present —
        // copying "photo (copy).jpg" should yield "photo (copy 2).jpg",
        // not "photo (copy) (copy).jpg".
        $base = preg_replace('/ \(copy(?: \d+)?\)$/', '', $base);

        for ($i = 1; $i <= 1000; $i++) {
            $suffix = $i === 1 ? ' (copy)' : " (copy {$i})";
            $candidate = $base.$suffix.$extPart;

            $exists = $this->mediaModel()::query()
                ->where('model_type', $ownerType)
                ->where('model_id', $ownerId)
                ->where('folder_id', $folderId)
                ->where('file_name', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        // Fallback (1000+ copies — astronomically unlikely)
        return $base.' (copy '.uniqid().')'.$extPart;
    }
}

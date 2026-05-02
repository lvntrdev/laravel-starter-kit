<?php

namespace App\Http\Controllers;

use App\Domain\FileManager\Actions\AddFavoriteAction;
use App\Domain\FileManager\Actions\BulkDeleteAction;
use App\Domain\FileManager\Actions\CopyFileAction;
use App\Domain\FileManager\Actions\CreateFolderAction;
use App\Domain\FileManager\Actions\DeleteFileAction;
use App\Domain\FileManager\Actions\DeleteFolderAction;
use App\Domain\FileManager\Actions\DownloadFileAction;
use App\Domain\FileManager\Actions\EmptyTrashAction;
use App\Domain\FileManager\Actions\MoveItemAction;
use App\Domain\FileManager\Actions\PermanentlyDeleteItemAction;
use App\Domain\FileManager\Actions\RemoveFavoriteAction;
use App\Domain\FileManager\Actions\RenameFileAction;
use App\Domain\FileManager\Actions\RenameFolderAction;
use App\Domain\FileManager\Actions\RestoreItemAction;
use App\Domain\FileManager\Actions\UploadFileAction;
use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\FileManager\Queries\FavoritesContentsQuery;
use App\Domain\FileManager\Queries\FolderContentsQuery;
use App\Domain\FileManager\Queries\FolderTreeQuery;
use App\Domain\FileManager\Queries\TrashContentsQuery;
use App\Domain\FileManager\Services\FileManagerAuthorizer;
use App\Exceptions\ApiException;
use App\Http\Requests\FileManager\BulkDeleteRequest;
use App\Http\Requests\FileManager\CopyFileRequest;
use App\Http\Requests\FileManager\DeleteFolderRequest;
use App\Http\Requests\FileManager\FavoriteRequest;
use App\Http\Requests\FileManager\FileManagerContextRequest;
use App\Http\Requests\FileManager\MoveItemRequest;
use App\Http\Requests\FileManager\RenameFileRequest;
use App\Http\Requests\FileManager\StoreFolderRequest;
use App\Http\Requests\FileManager\TrashItemRequest;
use App\Http\Requests\FileManager\UpdateFolderRequest;
use App\Http\Requests\FileManager\UploadFileRequest;
use App\Http\Responses\ApiResponse;
use App\Models\FileFolder;
use App\Models\Media;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagerController extends Controller
{
    public function __construct(
        private readonly FileManagerAuthorizer $authorizer,
    ) {}

    public function tree(FileManagerContextRequest $request, FolderTreeQuery $query): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeRead($context);

        return to_api(['tree' => $query->execute($context)]);
    }

    public function contents(FileManagerContextRequest $request, FolderContentsQuery $query): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeRead($context);

        $folderId = $request->query('folder_id');
        $folderId = $folderId === '' ? null : $folderId;

        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');

        return to_api($query->execute($context, $folderId, [
            'sort' => in_array($sort, ['name', 'size', 'date'], true) ? $sort : 'name',
            'direction' => $direction === 'desc' ? 'desc' : 'asc',
        ]));
    }

    public function bulkDelete(BulkDeleteRequest $request, BulkDeleteAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        /** @var array<int, array{type: string, id: string}> $items */
        $items = $request->input('items', []);

        $force = (bool) $request->input('force_delete', false);

        try {
            $result = $action->execute($context, $items, $force);
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        $message = $force ? __('sk-file-manager.bulk_force_deleted') : __('sk-file-manager.bulk_deleted');

        return to_api($result, $message);
    }

    public function createFolder(StoreFolderRequest $request, CreateFolderAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $folder = $action->execute(
                context: $context,
                name: $request->string('name')->toString(),
                parentId: $request->input('parent_id'),
            );
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(['folder' => [
            'id' => (string) $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
        ]], __('sk-file-manager.folder_created'), 201);
    }

    public function renameFolder(UpdateFolderRequest $request, FileFolder $folder, RenameFolderAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $folder = $action->execute($context, $folder, $request->string('name')->toString());
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(['folder' => [
            'id' => (string) $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
        ]], __('sk-file-manager.folder_renamed'));
    }

    public function renameFile(RenameFileRequest $request, Media $media, RenameFileAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $media = $action->execute($context, $media, $request->string('name')->toString());
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(['file' => [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'name' => $media->name,
        ]], __('sk-file-manager.file_renamed'));
    }

    public function copyFile(CopyFileRequest $request, Media $media, CopyFileAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $copy = $action->execute($context, $media, $request->input('target_folder_id'));
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(['file' => [
            'id' => $copy->id,
            'file_name' => $copy->file_name,
            'name' => $copy->name,
            'folder_id' => $copy->folder_id,
        ]], __('sk-file-manager.file_copied'), 201);
    }

    public function favoritesContents(FileManagerContextRequest $request, FavoritesContentsQuery $query): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeRead($context);

        return to_api($query->execute($context));
    }

    public function addFavorite(FavoriteRequest $request, AddFavoriteAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $action->execute(
                context: $context,
                favoritableType: $request->string('favoritable_type')->toString(),
                favoritableId: $request->string('favoritable_id')->toString(),
            );
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(null, __('sk-file-manager.favorite_added'), 201);
    }

    public function removeFavorite(FavoriteRequest $request, RemoveFavoriteAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        $action->execute(
            context: $context,
            favoritableType: $request->string('favoritable_type')->toString(),
            favoritableId: $request->string('favoritable_id')->toString(),
        );

        return to_api(message: __('sk-file-manager.favorite_removed'));
    }

    public function trashContents(FileManagerContextRequest $request, TrashContentsQuery $query): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeRead($context);

        return to_api($query->execute($context));
    }

    public function emptyTrash(FileManagerContextRequest $request, EmptyTrashAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        $result = $action->execute($context);

        return to_api($result, __('sk-file-manager.trash_emptied'));
    }

    public function restoreItem(TrashItemRequest $request, RestoreItemAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $action->execute(
                context: $context,
                itemType: $request->string('item_type')->toString(),
                itemId: $request->string('item_id')->toString(),
            );
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(message: __('sk-file-manager.item_restored'));
    }

    public function permanentlyDeleteItem(TrashItemRequest $request, PermanentlyDeleteItemAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $action->execute(
                context: $context,
                itemType: $request->string('item_type')->toString(),
                itemId: $request->string('item_id')->toString(),
            );
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(message: __('sk-file-manager.item_permanently_deleted'));
    }

    public function moveItem(MoveItemRequest $request, MoveItemAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $action->execute(
                context: $context,
                itemType: $request->string('item_type')->toString(),
                itemId: (string) $request->input('item_id'),
                targetFolderId: $request->input('target_folder_id'),
            );
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(message: __('sk-file-manager.item_moved'));
    }

    public function deleteFolder(DeleteFolderRequest $request, FileFolder $folder, DeleteFolderAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $action->execute($context, $folder);
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(message: __('sk-file-manager.folder_deleted'));
    }

    public function upload(UploadFileRequest $request, UploadFileAction $action): ApiResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeWrite($context);

        try {
            $uploaded = $action->execute(
                context: $context,
                files: $request->file('files') ?? [],
                folderId: $request->input('folder_id'),
                folderName: $request->input('folder_name'),
            );
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(['files' => $uploaded], __('sk-file-manager.files_uploaded'), 201);
    }

    public function deleteFile(Request $request, Media $media, DeleteFileAction $action): ApiResponse
    {
        $context = $this->contextFromRequest($request);
        $this->authorizer->authorizeWrite($context);

        try {
            $action->execute($context, $media);
        } catch (LogicException $e) {
            throw ApiException::unprocessable($e->getMessage());
        }

        return to_api(message: __('sk-file-manager.file_deleted'));
    }

    public function download(FileManagerContextRequest $request, Media $media, DownloadFileAction $action): BinaryFileResponse|StreamedResponse
    {
        $context = $request->context();
        $this->authorizer->authorizeRead($context);

        return $action->execute($context, $media);
    }

    private function contextFromRequest(Request $request): FileManagerContextDTO
    {
        return FileManagerContextDTO::fromArray([
            'context' => (string) $request->input('context', $request->query('context')),
            'context_id' => $request->input('context_id', $request->query('context_id')),
        ]);
    }
}

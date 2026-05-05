<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\FileManagerController;

/*
|--------------------------------------------------------------------------
| FileManager Vendor Routes
|--------------------------------------------------------------------------
|
| Mounted by Lvntr\StarterKit\Facades\FileManager::routes(). This file is
| intentionally inert — it only declares the route group with prefix, name,
| and controller. No outer middleware is attached so the caller (consumer
| route orchestrator or package ServiceProvider auto-mount) controls the
| auth/permission stack.
|
| Route names, URL prefix, HTTP methods and parameter names match v13.4.x
| 1:1 — Wayfinder typed routes and existing axios calls keep working.
|
| Route model binding for {folder} is resolved from
| config('file-manager.models.folder') so apps that swap the FileFolder
| model (config-driven binding from Task 5) keep working with vendor
| controller signatures that use the abstract Model type-hint.
|
| Spatie Media uses implicit binding; nothing to wire up for {media}.
|
*/

$folderModel = config('file-manager.models.folder');

if (is_string($folderModel) && $folderModel !== '' && is_subclass_of($folderModel, Model::class)) {
    Route::model('folder', $folderModel);
}

Route::prefix('file-manager')
    ->name('file-manager.')
    ->controller(FileManagerController::class)
    ->group(function (): void {
        Route::get('tree', 'tree')->name('tree');
        Route::get('contents', 'contents')->name('contents');

        Route::get('favorites/contents', 'favoritesContents')->name('favorites.contents');
        Route::post('favorites', 'addFavorite')->name('favorites.add');
        Route::delete('favorites', 'removeFavorite')->name('favorites.remove');

        Route::get('trash/contents', 'trashContents')->name('trash.contents');
        Route::delete('trash/empty', 'emptyTrash')->name('trash.empty');
        Route::post('items/restore', 'restoreItem')->name('items.restore');
        Route::delete('items/permanent', 'permanentlyDeleteItem')->name('items.permanent');

        Route::post('folders', 'createFolder')->name('folders.store');
        Route::patch('folders/{folder}', 'renameFolder')->name('folders.rename');
        Route::delete('folders/{folder}', 'deleteFolder')->name('folders.destroy');

        Route::patch('items/move', 'moveItem')->name('items.move');
        Route::post('items/bulk-delete', 'bulkDelete')->name('items.bulkDelete');

        Route::post('files', 'upload')->middleware('throttle:30,1')->name('files.upload');
        Route::patch('files/{media}', 'renameFile')->name('files.rename');
        Route::post('files/{media}/copy', 'copyFile')->name('files.copy');
        Route::delete('files/{media}', 'deleteFile')->name('files.destroy');
        Route::get('files/{media}/download', 'download')->name('files.download');
    });

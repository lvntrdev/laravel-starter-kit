<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\FileManager\ShareController;
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

/*
|--------------------------------------------------------------------------
| FileManager Share Routes
|--------------------------------------------------------------------------
|
| config('file-manager.share.enabled') false ise bu grup hiç register
| edilmez. Böylece feature flag ile tamamen kapatılabilir.
|
| GET  file-manager/share/{media} — public endpoint; signed middleware ile
|                                   korunur, auth gerektirmez.
| POST file-manager/share          — link üret; auth gerekli.
| POST file-manager/share/revoke   — revoke et; auth gerekli.
|
*/

if (config('file-manager.share.enabled', true)) {
    // Public: signed URL ile korunan serve endpoint'i — auth gerektirmez.
    //
    // K1 (security): withoutMiddleware(['auth', 'verified']) ile outer auth
    // grubundan muaf tutulur. Bu sayede FileManager::routes() herhangi bir
    // auth middleware wrapper içinde çağrılsa bile anonymous kullanıcılar
    // signed URL ile bu endpoint'e erişebilir. Endpoint yalnızca `signed`
    // (imza doğrulaması) ve `throttle:60,1` (rate limit) ile korunur.
    Route::get('file-manager/share/{media}', [ShareController::class, 'show'])
        ->name('file-manager.share.show')
        ->middleware(['signed', 'throttle:60,1'])
        ->withoutMiddleware(['auth', 'verified', 'auth:sanctum', 'auth:api']);

    // Auth gerekli: link üretme ve revoke.
    Route::prefix('file-manager/share')
        ->name('file-manager.share.')
        ->controller(ShareController::class)
        ->middleware('throttle:30,1')
        ->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::post('/revoke', 'revoke')->name('revoke');
        });
}

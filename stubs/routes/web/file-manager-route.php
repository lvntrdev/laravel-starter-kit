<?php

use App\Http\Controllers\FileManagerController;
use Illuminate\Support\Facades\Route;

Route::prefix('file-manager')
    ->name('file-manager.')
    ->controller(FileManagerController::class)
    ->group(function () {
        Route::get('tree', 'tree')->name('tree');
        Route::get('contents', 'contents')->name('contents');

        Route::post('folders', 'createFolder')->name('folders.store');
        Route::patch('folders/{folder}', 'renameFolder')->name('folders.rename');
        Route::delete('folders/{folder}', 'deleteFolder')->name('folders.destroy');

        Route::patch('items/move', 'moveItem')->name('items.move');
        Route::post('items/bulk-delete', 'bulkDelete')->name('items.bulkDelete');

        Route::post('files', 'upload')->middleware('throttle:30,1')->name('files.upload');
        Route::delete('files/{media}', 'deleteFile')->name('files.destroy');
        Route::get('files/{media}/download', 'download')->name('files.download');
    });

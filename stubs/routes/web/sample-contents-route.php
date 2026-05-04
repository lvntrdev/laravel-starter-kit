<?php

use App\Http\Controllers\Admin\SampleContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('sample-contents')->name('sample-contents.')->controller(SampleContentController::class)->group(function () {
    Route::get('dt', 'dtApi')->name('dtApi');
    Route::get('{sample_content}/data', 'data')->name('data');
});

Route::resource('sample-contents', SampleContentController::class)
    ->parameters(['sample-contents' => 'sample_content'])
    ->except(['show']);

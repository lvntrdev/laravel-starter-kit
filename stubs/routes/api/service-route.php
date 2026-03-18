<?php

use App\Http\Controllers\Api\DefinitionController;
use Illuminate\Support\Facades\Route;

Route::get('definitions', [DefinitionController::class, 'index'])->name('definitions.index');

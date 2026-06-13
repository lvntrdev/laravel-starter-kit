<?php

use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\Api\DefinitionController;

Route::get('definitions', [DefinitionController::class, 'index'])->name('definitions.index');

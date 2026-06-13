<?php

use App\Http\Controllers\Service\RoleServiceController;
use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\Service\DefinitionServiceController;

Route::get('definitions', [DefinitionServiceController::class, 'index'])->name('definitions.index');
Route::get('roles/options', [RoleServiceController::class, 'getRoles'])->name('roles.roleOptions');

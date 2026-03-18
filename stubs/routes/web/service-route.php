<?php

use App\Http\Controllers\Service\DefinitionServiceController;
use App\Http\Controllers\Service\RoleServiceController;
use Illuminate\Support\Facades\Route;

Route::get('definitions', [DefinitionServiceController::class, 'index'])->name('definitions.index');
Route::get('roles/options', [RoleServiceController::class, 'getRoles'])->name('roles.roleOptions');

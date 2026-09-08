<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/manifest.webmanifest', '/manifest.json', 301);

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        Route::middleware('permission:roles.view')->group(function () {
            Route::get('roles', [App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
        });
        Route::middleware('permission:roles.export')->get('roles/export', [App\Http\Controllers\RoleController::class, 'export'])->name('roles.export');
        Route::middleware('permission:roles.create')->post('roles', [App\Http\Controllers\RoleController::class, 'store'])->name('roles.store');
        Route::middleware('permission:roles.bulk-delete')->delete('roles/bulk', [App\Http\Controllers\RoleController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
        Route::middleware('permission:roles.update')->put('roles/{role}/permissions', [App\Http\Controllers\RoleController::class, 'updatePermissions'])->name('roles.update-permissions');
        Route::middleware('permission:roles.update')->put('roles/{role}', [App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');
        Route::middleware('permission:roles.delete')->delete('roles/{role}', [App\Http\Controllers\RoleController::class, 'destroy'])->name('roles.destroy');

        Route::middleware('permission:usuarios.view')->group(function () {
            Route::get('usuarios', [App\Http\Controllers\UserController::class, 'index'])->name('usuarios.index');
        });
        Route::middleware('permission:usuarios.export')->get('usuarios/export', [App\Http\Controllers\UserController::class, 'export'])->name('usuarios.export');
        Route::middleware('permission:usuarios.create')->post('usuarios', [App\Http\Controllers\UserController::class, 'store'])->name('usuarios.store');
        Route::middleware('permission:usuarios.bulk-delete')->delete('usuarios/bulk', [App\Http\Controllers\UserController::class, 'bulkDestroy'])->name('usuarios.bulk-destroy');
        Route::middleware('permission:usuarios.update')->put('usuarios/{user}/documentos', [App\Http\Controllers\UserController::class, 'updateDocuments'])->name('usuarios.update-documents');
        Route::middleware('permission:usuarios.update')->put('usuarios/{user}', [App\Http\Controllers\UserController::class, 'update'])->name('usuarios.update');
        Route::middleware('permission:usuarios.delete')->delete('usuarios/{user}', [App\Http\Controllers\UserController::class, 'destroy'])->name('usuarios.destroy');
    });
});

require __DIR__.'/settings.php';

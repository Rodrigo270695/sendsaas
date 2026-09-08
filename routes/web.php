<?php

use App\Http\Controllers\PlanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/manifest.webmanifest', '/manifest.json', 301);

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        Route::middleware('permission:roles.view')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        });
        Route::middleware('permission:roles.export')->get('roles/export', [RoleController::class, 'export'])->name('roles.export');
        Route::middleware('permission:roles.create')->post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::middleware('permission:roles.bulk-delete')->delete('roles/bulk', [RoleController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
        Route::middleware('permission:roles.update')->put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.update-permissions');
        Route::middleware('permission:roles.update')->put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::middleware('permission:roles.delete')->delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::middleware('permission:usuarios.view')->group(function () {
            Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
        });
        Route::middleware('permission:usuarios.export')->get('usuarios/export', [UserController::class, 'export'])->name('usuarios.export');
        Route::middleware('permission:usuarios.create')->post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::middleware('permission:usuarios.bulk-delete')->delete('usuarios/bulk', [UserController::class, 'bulkDestroy'])->name('usuarios.bulk-destroy');
        Route::middleware('permission:usuarios.update')->put('usuarios/{user}/documentos', [UserController::class, 'updateDocuments'])->name('usuarios.update-documents');
        Route::middleware('permission:usuarios.update')->put('usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
        Route::middleware('permission:usuarios.delete')->delete('usuarios/{user}', [UserController::class, 'destroy'])->name('usuarios.destroy');
    });

    Route::prefix('plataforma')->name('plataforma.')->group(function () {
        Route::middleware('permission:plataforma-planes.view')->group(function () {
            Route::get('planes', [PlanController::class, 'index'])->name('planes.index');
        });
        Route::middleware('permission:plataforma-planes.export')->get('planes/export', [PlanController::class, 'export'])->name('planes.export');
        Route::middleware('permission:plataforma-planes.create')->post('planes', [PlanController::class, 'store'])->name('planes.store');
        Route::middleware('permission:plataforma-planes.bulk-delete')->delete('planes/bulk', [PlanController::class, 'bulkDestroy'])->name('planes.bulk-destroy');
        Route::middleware('permission:plataforma-planes.update')->put('planes/{plan}/features', [PlanController::class, 'updateFeatures'])->name('planes.update-features');
        Route::middleware('permission:plataforma-planes.update')->put('planes/{plan}', [PlanController::class, 'update'])->name('planes.update');
        Route::middleware('permission:plataforma-planes.delete')->delete('planes/{plan}', [PlanController::class, 'destroy'])->name('planes.destroy');
    });
});

require __DIR__.'/settings.php';

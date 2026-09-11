<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\OutboundQueueController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantImpersonationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/manifest.webmanifest', '/manifest.json', 301);

Route::redirect('/', '/login')->name('home');

Route::get('impersonate/accept', [TenantImpersonationController::class, 'accept'])
    ->middleware('throttle:12,1')
    ->name('impersonate.accept');

Route::get('impersonate/return', [TenantImpersonationController::class, 'returnToCentral'])
    ->middleware('throttle:12,1')
    ->name('impersonate.return');

Route::middleware(['auth', 'verified', 'tenant.match-user'])->group(function () {
    Route::post('impersonate/leave', [TenantImpersonationController::class, 'leave'])
        ->name('impersonate.leave');
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('geo/departamentos', [GeoController::class, 'departamentos'])->name('geo.departamentos');
    Route::get('geo/provincias', [GeoController::class, 'provincias'])->name('geo.provincias');
    Route::get('geo/distritos', [GeoController::class, 'distritos'])->name('geo.distritos');

    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        Route::middleware('permission:settings.view')->get('suscripcion', [SubscriptionController::class, 'show'])->name('suscripcion.show');

        Route::middleware('permission:sedes.view')->get('sedes', [SedeController::class, 'index'])->name('sedes.index');
        Route::middleware('permission:sedes.export')->get('sedes/export', [SedeController::class, 'export'])->name('sedes.export');
        Route::middleware('permission:sedes.create')->post('sedes', [SedeController::class, 'store'])->name('sedes.store');
        Route::middleware('permission:sedes.bulk-delete')->delete('sedes/bulk', [SedeController::class, 'bulkDestroy'])->name('sedes.bulk-destroy');
        Route::middleware('permission:sedes.update')->put('sedes/{sede}', [SedeController::class, 'update'])->name('sedes.update');
        Route::middleware('permission:sedes.delete')->delete('sedes/{sede}', [SedeController::class, 'destroy'])->name('sedes.destroy');

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
        Route::get('usuarios/consulta-dni', [UserController::class, 'consultaDni'])
            ->middleware('throttle:30,1')
            ->name('usuarios.consulta-dni');
        Route::middleware('permission:usuarios.export')->get('usuarios/export', [UserController::class, 'export'])->name('usuarios.export');
        Route::middleware('permission:usuarios.create')->post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::middleware('permission:usuarios.bulk-delete')->delete('usuarios/bulk', [UserController::class, 'bulkDestroy'])->name('usuarios.bulk-destroy');
        Route::middleware('permission:usuarios.update')->put('usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
        Route::middleware('permission:usuarios.delete')->delete('usuarios/{user}', [UserController::class, 'destroy'])->name('usuarios.destroy');
    });

    Route::prefix('comunicaciones')->name('comunicaciones.')->group(function () {
        Route::middleware('permission:whatsapp.view')->get('sesiones', [WhatsappSessionController::class, 'index'])->name('sesiones.index');
        Route::middleware('permission:whatsapp.connect')->post('sesiones', [WhatsappSessionController::class, 'store'])->name('sesiones.store');
        Route::middleware('permission:whatsapp.connect')->post('sesiones/{whatsappSession}/connect', [WhatsappSessionController::class, 'connect'])->name('sesiones.connect');
        Route::middleware('permission:whatsapp.connect')->get('sesiones/{whatsappSession}/qr', [WhatsappSessionController::class, 'qr'])->name('sesiones.qr');
        Route::middleware('permission:whatsapp.connect')->post('sesiones/{whatsappSession}/disconnect', [WhatsappSessionController::class, 'disconnect'])->name('sesiones.disconnect');
        Route::middleware('permission:whatsapp.update')->put('sesiones/{whatsappSession}', [WhatsappSessionController::class, 'update'])->name('sesiones.update');
        Route::middleware('permission:whatsapp.delete')->delete('sesiones/{whatsappSession}', [WhatsappSessionController::class, 'destroy'])->name('sesiones.destroy');

        Route::middleware('permission:comunicaciones.envios.view')
            ->get('envios', [OutboundQueueController::class, 'index'])
            ->name('envios.index');
        Route::middleware('permission:comunicaciones.historial.view')
            ->get('historial', fn () => Inertia::render('comunicaciones/historial/index'))
            ->name('historial.index');
    });

    Route::prefix('bandeja')->name('bandeja.')->group(function () {
        Route::middleware('permission:conversations.view')->get('conversaciones', [ConversationController::class, 'index'])->name('conversaciones.index');
        Route::middleware('permission:conversations.view')->get('conversaciones/{conversation}', [ConversationController::class, 'show'])->name('conversaciones.show');
        Route::middleware('permission:conversations.reply')->post('conversaciones/{conversation}/mensajes', [ConversationController::class, 'reply'])->name('conversaciones.reply');
    });

    Route::prefix('contactos')->name('contactos.')->group(function () {
        Route::middleware('permission:contacts.view')->get('/', [ContactController::class, 'index'])->name('index');
        Route::middleware('permission:contacts.export')->get('export', [ContactController::class, 'export'])->name('export');
        Route::middleware('permission:contacts.create')->get('plantilla', [ContactController::class, 'template'])->name('template');
        Route::middleware('permission:contacts.create')->post('import', [ContactController::class, 'import'])->name('import');
        Route::middleware('permission:contacts.create')->post('/', [ContactController::class, 'store'])->name('store');
        Route::middleware('permission:contacts.update')->put('{contact}', [ContactController::class, 'update'])->name('update');
        Route::middleware('permission:contacts.delete')->delete('{contact}', [ContactController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('plataforma')->name('plataforma.')->middleware('tenant.central')->group(function () {
        Route::middleware('permission:plataforma-planes.view')->group(function () {
            Route::get('planes', [PlanController::class, 'index'])->name('planes.index');
        });
        Route::middleware('permission:plataforma-planes.export')->get('planes/export', [PlanController::class, 'export'])->name('planes.export');
        Route::middleware('permission:plataforma-planes.create')->post('planes', [PlanController::class, 'store'])->name('planes.store');
        Route::middleware('permission:plataforma-planes.bulk-delete')->delete('planes/bulk', [PlanController::class, 'bulkDestroy'])->name('planes.bulk-destroy');
        Route::middleware('permission:plataforma-planes.update')->put('planes/{plan}/features', [PlanController::class, 'updateFeatures'])->name('planes.update-features');
        Route::middleware('permission:plataforma-planes.update')->put('planes/{plan}', [PlanController::class, 'update'])->name('planes.update');
        Route::middleware('permission:plataforma-planes.delete')->delete('planes/{plan}', [PlanController::class, 'destroy'])->name('planes.destroy');

        Route::middleware('permission:plataforma-tenants.view')->get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::middleware('permission:plataforma-tenants.create')->post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::middleware('permission:plataforma-tenants.update')->put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::middleware('permission:plataforma-tenants.suspend')->post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::middleware('permission:plataforma-tenants.resume')->post('tenants/{tenant}/resume', [TenantController::class, 'resume'])->name('tenants.resume');
        Route::middleware('permission:plataforma-tenants.delete')->delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::middleware('permission:plataforma-tenants.impersonate')->post('tenants/{tenant}/impersonate', [TenantImpersonationController::class, 'start'])->name('tenants.impersonate');
    });
});

require __DIR__.'/settings.php';

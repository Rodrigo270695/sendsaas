<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Catálogo global. Convención: modulo.accion
     *
     * @var list<string>
     */
    public const CATALOG = [
        'dashboard.view',
        'settings.view',
        'settings.update',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'usuarios.view',
        'usuarios.create',
        'usuarios.update',
        'usuarios.delete',
        'usuarios.export',
        'usuarios.bulk-delete',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.export',
        'roles.bulk-delete',
        'conversations.view',
        'conversations.assign',
        'conversations.reply',
        'contacts.view',
        'contacts.create',
        'contacts.update',
        'whatsapp.view',
        'whatsapp.connect',
        'campaigns.view',
        'campaigns.create',
        'automations.view',
        'automations.manage',
        'reports.view',
        'plataforma-tenants.view',
        'plataforma-tenants.create',
        'plataforma-tenants.update',
        'plataforma-tenants.suspend',
        'plataforma-planes.view',
        'plataforma-planes.create',
        'plataforma-planes.update',
        'plataforma-planes.delete',
        'plataforma-planes.export',
        'plataforma-planes.bulk-delete',
        'plataforma-openwa.view',
        'plataforma-openwa.manage',
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}

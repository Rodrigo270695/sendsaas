<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('superadmin is a platform role with a dedicated user', function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);

    $user = User::query()->where('email', SuperadminSeeder::EMAIL)->first();
    $role = Role::query()->where('name', 'superadmin')->whereNull('tenant_id')->first();

    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBeNull()
        ->and($user->isCentral())->toBeTrue()
        ->and($user->isPlatformSuperadmin())->toBeTrue()
        ->and($user->can('plataforma-tenants.create'))->toBeTrue()
        ->and($role)->not->toBeNull()
        ->and($role->tenant_id)->toBeNull();
});

test('tenant roles are created per empresa and stay separate from superadmin', function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);

    $tenant = Tenant::factory()->create();

    (new TenantRolesSeeder)->seedForTenant((string) $tenant->id);

    $tenantRoles = Role::query()
        ->where('tenant_id', $tenant->id)
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($tenantRoles)->toBe(['admin_empresa', 'agente', 'supervisor'])
        ->and(Role::query()->where('name', 'superadmin')->where('tenant_id', $tenant->id)->exists())->toBeFalse()
        ->and(Role::query()->where('name', 'admin_empresa')->whereNull('tenant_id')->exists())->toBeFalse();

    $agent = User::factory()->create(['tenant_id' => $tenant->id]);
    $previous = getPermissionsTeamId();
    setPermissionsTeamId((string) $tenant->id);

    try {
        $agent->assignRole('agente');
        expect($agent->hasRole('agente'))->toBeTrue()
            ->and($agent->can('conversations.reply'))->toBeTrue()
            ->and($agent->can('plataforma-tenants.create'))->toBeFalse()
            ->and($agent->isPlatformSuperadmin())->toBeFalse();
    } finally {
        setPermissionsTeamId($previous);
    }
});

<?php

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
});

test('superadmin can view the users index', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('configuracion.usuarios.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuracion/usuarios/index')
            ->has('users.data')
            ->has('stats')
            ->has('roles_catalog')
        );
});

test('user without permission cannot view users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('configuracion.usuarios.index'))
        ->assertForbidden();
});

test('a user cannot delete their own account', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->from(route('configuracion.usuarios.index'))
        ->delete(route('configuracion.usuarios.destroy', $admin))
        ->assertRedirect(route('configuracion.usuarios.index'))
        ->assertSessionHasErrors('email');

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('platform superadmin cannot be deleted by another user', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();
    $other = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($other)
        ->delete(route('configuracion.usuarios.destroy', $admin))
        ->assertForbidden();

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

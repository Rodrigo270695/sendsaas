<?php

namespace Database\Factories;

use App\Models\Sede;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    protected $model = Sede::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->company(),
            'codigo' => 'SEDE-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'direccion' => fake()->streetAddress(),
            'telefono' => '999888777',
            'email' => fake()->optional()->companyEmail(),
            'distrito_id' => null,
            'distrito' => null,
            'provincia' => null,
            'departamento' => null,
            'activa' => true,
        ];
    }
}

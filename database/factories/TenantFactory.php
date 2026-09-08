<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $slug = 'empresa-'.Str::lower(Str::random(6));

        return [
            'slug' => $slug,
            'schema_name' => 'od_'.str_replace('-', '_', $slug),
            'razon_social' => fake()->company(),
            'nombre_comercial' => fake()->company(),
            'email_admin' => fake()->unique()->companyEmail(),
            'estado' => 'active',
            'timezone' => 'America/Lima',
            'locale' => 'es_PE',
        ];
    }
}

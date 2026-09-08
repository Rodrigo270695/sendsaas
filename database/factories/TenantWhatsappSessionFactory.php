<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantWhatsappSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantWhatsappSession>
 */
class TenantWhatsappSessionFactory extends Factory
{
    protected $model = TenantWhatsappSession::class;

    public function definition(): array
    {
        $slug = 'wa-'.fake()->unique()->numerify('###');

        return [
            'tenant_id' => Tenant::factory(),
            'sede_id' => null,
            'alias' => 'WhatsApp '.fake()->city(),
            'openwa_session_id' => null,
            'openwa_session_name' => $slug,
            'status' => 'created',
            'phone' => null,
            'push_name' => null,
            'auto_reconnect' => true,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => 'ready',
            'phone' => '519'.fake()->numerify('########'),
            'push_name' => fake()->firstName(),
            'connected_at' => now(),
            'last_synced_at' => now(),
        ]);
    }
}

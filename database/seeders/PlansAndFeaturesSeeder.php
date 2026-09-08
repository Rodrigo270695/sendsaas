<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlansAndFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'free' => [
                'nombre' => 'Free',
                'descripcion' => 'Bandeja y contactos para probar SendSaaS sin compromiso.',
                'badge' => null,
                'color_hex' => '#6B7280',
                'precio_mensual' => 0,
                'trial_days' => 0,
                'orden' => 1,
                'features' => [
                    ...self::limits(sedes: 1, usuarios: 1, wa: 1, day: 50, contacts: 100, campaigns: 0, automations: 0),
                    ...self::envio('08:00', '20:00', 'docs'),
                ],
            ],
            'starter' => [
                'nombre' => 'Starter',
                'descripcion' => 'Un número de WhatsApp, 2 usuarios y 500 mensajes salientes por día.',
                'badge' => null,
                'color_hex' => '#AB3C3D',
                'precio_mensual' => 49,
                'trial_days' => 14,
                'orden' => 2,
                'features' => [
                    ...self::limits(sedes: 1, usuarios: 2, wa: 1, day: 500, contacts: 1000, campaigns: 2, automations: 0),
                    ...self::envio('08:00', '20:00', 'email'),
                ],
            ],
            'profesional' => [
                'nombre' => 'Profesional',
                'descripcion' => '5 usuarios, 1 sesión de WhatsApp y 1500 mensajes salientes por día.',
                'badge' => 'Más popular',
                'color_hex' => '#1D4ED8',
                'precio_mensual' => 99,
                'trial_days' => 14,
                'orden' => 3,
                'features' => [
                    ...self::limits(sedes: 1, usuarios: 5, wa: 1, day: 1500, contacts: 5000, campaigns: 10, automations: 10),
                    ...self::envio('08:00', '20:00', 'whatsapp'),
                ],
            ],
            'business' => [
                'nombre' => 'Business',
                'descripcion' => '15 usuarios, 2 sesiones de WhatsApp y 5000 mensajes salientes por día.',
                'badge' => 'Mejor valor',
                'color_hex' => '#7C3AED',
                'precio_mensual' => 199,
                'trial_days' => 7,
                'orden' => 4,
                'features' => [
                    ...self::limits(sedes: 2, usuarios: 15, wa: 2, day: 5000, contacts: -1, campaigns: 50, automations: 30),
                    ...self::envio('08:00', '20:00', 'whatsapp'),
                ],
            ],
            'enterprise' => [
                'nombre' => 'Enterprise',
                'descripcion' => 'Usuarios, sesiones de WhatsApp y mensajes ilimitados. Soporte prioritario.',
                'badge' => null,
                'color_hex' => '#0F6E56',
                'precio_mensual' => 399,
                'trial_days' => 7,
                'orden' => 5,
                'features' => [
                    ...self::limits(sedes: -1, usuarios: -1, wa: -1, day: -1, contacts: -1, campaigns: -1, automations: -1),
                    ...self::envio('08:00', '20:00', 'whatsapp_prioritario'),
                ],
            ],
        ];

        foreach ($definitions as $codigo => $meta) {
            $plan = Plan::query()->updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $meta['nombre'],
                    'descripcion' => $meta['descripcion'],
                    'badge' => $meta['badge'],
                    'color_hex' => $meta['color_hex'],
                    'precio_mensual' => $meta['precio_mensual'],
                    'precio_anual' => Plan::precioAnualDesdeMensual($meta['precio_mensual']),
                    'trial_days' => $meta['trial_days'],
                    'orden' => $meta['orden'],
                    'es_publico' => true,
                    'activo' => true,
                ],
            );

            $plan->features()->delete();

            foreach ($meta['features'] as $row) {
                PlanFeature::query()->create([
                    'plan_id' => $plan->id,
                    'feature' => $row['feature'],
                    'valor_int' => $row['valor_int'],
                    'valor_bool' => $row['valor_bool'],
                    'valor_str' => $row['valor_str'],
                ]);
            }
        }
    }

    /**
     * @return list<array{feature: string, valor_int: int, valor_bool: null, valor_str: null}>
     */
    private static function limits(
        int $sedes,
        int $usuarios,
        int $wa,
        int $day,
        int $contacts,
        int $campaigns,
        int $automations,
    ): array {
        return [
            ['feature' => 'max_sedes', 'valor_int' => $sedes, 'valor_bool' => null, 'valor_str' => null],
            ['feature' => 'max_usuarios', 'valor_int' => $usuarios, 'valor_bool' => null, 'valor_str' => null],
            ['feature' => 'max_whatsapp_sessions', 'valor_int' => $wa, 'valor_bool' => null, 'valor_str' => null],
            ['feature' => 'max_outbound_per_day', 'valor_int' => $day, 'valor_bool' => null, 'valor_str' => null],
            ['feature' => 'max_contacts', 'valor_int' => $contacts, 'valor_bool' => null, 'valor_str' => null],
            ['feature' => 'max_campaigns', 'valor_int' => $campaigns, 'valor_bool' => null, 'valor_str' => null],
            ['feature' => 'max_automations', 'valor_int' => $automations, 'valor_bool' => null, 'valor_str' => null],
        ];
    }

    /**
     * @return list<array{feature: string, valor_int: null, valor_bool: null, valor_str: string}>
     */
    private static function envio(string $start, string $end, string $soporte): array
    {
        return [
            ['feature' => 'send_window_start', 'valor_int' => null, 'valor_bool' => null, 'valor_str' => $start],
            ['feature' => 'send_window_end', 'valor_int' => null, 'valor_bool' => null, 'valor_str' => $end],
            ['feature' => 'soporte_tipo', 'valor_int' => null, 'valor_bool' => null, 'valor_str' => $soporte],
        ];
    }
}

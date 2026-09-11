<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Tenant;
use App\Support\Plan\PlanLimits;
use Carbon\CarbonImmutable;

/**
 * Resumen de plan y fechas para Configuración → Mi suscripción.
 *
 * Todavía no hay tabla `subscriptions`: se deriva de tenant + plan.
 * El cobro automático (Orvae) llega después; el CTA va a WhatsApp.
 */
final class TenantSubscriptionSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function forTenant(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');
        $plan = $tenant->plan;
        $tz = self::timezone($tenant);
        $now = CarbonImmutable::now($tz);
        $estado = self::normalizeEstado((string) $tenant->estado);
        $anchor = self::resolveRenewalAnchor($tenant, $now, $estado, $tz);
        $daysUntil = $anchor === null
            ? null
            : (int) $now->startOfDay()->diffInDays($anchor->startOfDay(), false);

        $precio = $plan?->precio_mensual;

        return [
            'has_subscription' => $plan !== null,
            'plan' => $plan === null ? null : [
                'nombre' => $plan->nombre,
                'codigo' => $plan->codigo,
                'badge' => $plan->badge,
                'color_hex' => $plan->color_hex,
                'descripcion' => $plan->descripcion,
            ],
            'estado' => $estado,
            'ciclo' => 'mensual',
            'precio_pactado' => $precio !== null ? (string) $precio : null,
            'timezone' => $tz,
            'trial_ends_at' => $tenant->trial_ends_at?->timezone($tz)->toIso8601String(),
            'current_period_start' => $tenant->created_at?->timezone($tz)->toIso8601String(),
            'current_period_end' => $anchor?->toIso8601String(),
            'proximo_cobro_at' => $anchor?->toIso8601String(),
            'renewal_anchor_at' => $anchor?->toIso8601String(),
            'days_until_renewal' => $daysUntil,
            'urgency' => self::resolveUrgency($estado, $daysUntil, $plan === null),
            'renewal_url' => self::renewalWhatsAppUrl($tenant),
            'send_window' => [
                'start' => PlanLimits::stringValue($plan, 'send_window_start') ?? '08:00',
                'end' => PlanLimits::stringValue($plan, 'send_window_end') ?? '20:00',
            ],
            'soporte_tipo' => PlanLimits::stringValue($plan, 'soporte_tipo'),
        ];
    }

    private static function timezone(Tenant $tenant): string
    {
        $tz = trim((string) $tenant->timezone);

        return $tz !== '' ? $tz : (string) config('app.timezone', 'America/Lima');
    }

    private static function normalizeEstado(string $estado): string
    {
        return match ($estado) {
            'trial', 'active', 'grace', 'suspended', 'cancelled' => $estado,
            default => 'unknown',
        };
    }

    private static function resolveRenewalAnchor(
        Tenant $tenant,
        CarbonImmutable $now,
        string $estado,
        string $tz,
    ): ?CarbonImmutable {
        if ($tenant->trial_ends_at !== null) {
            $trial = CarbonImmutable::parse($tenant->trial_ends_at)->timezone($tz);
            if ($estado === 'trial' || $trial->greaterThan($now)) {
                return $trial;
            }
        }

        if (in_array($estado, ['suspended', 'cancelled'], true)) {
            $closedAt = $tenant->suspended_at ?? $tenant->cancelled_at;

            return $closedAt !== null
                ? CarbonImmutable::parse($closedAt)->timezone($tz)
                : null;
        }

        if ($tenant->created_at === null) {
            return null;
        }

        $anchor = CarbonImmutable::parse($tenant->created_at)->timezone($tz)->setTime(0, 0);

        while ($anchor->lessThanOrEqualTo($now)) {
            $anchor = $anchor->addMonthNoOverflow();
        }

        return $anchor;
    }

    private static function resolveUrgency(string $estado, ?int $daysUntil, bool $noPlan): string
    {
        if ($noPlan) {
            return 'muted';
        }

        if (in_array($estado, ['suspended', 'cancelled'], true)) {
            return 'danger';
        }

        if ($daysUntil === null) {
            return 'muted';
        }

        if ($daysUntil <= 2) {
            return 'red';
        }

        if ($daysUntil <= 5) {
            return 'amber';
        }

        if ($daysUntil <= 7) {
            return 'yellow';
        }

        return 'ok';
    }

    private static function renewalWhatsAppUrl(Tenant $tenant): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) config('app.contact_whatsapp', ''));
        if ($digits === null || $digits === '') {
            return null;
        }

        $label = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social));
        $plan = $tenant->plan?->nombre ?? 'SendSaaS';
        $empresa = $label !== '' ? $label : (string) $tenant->slug;
        $text = sprintf(
            'Hola, quiero renovar o cambiar el plan %s de %s.',
            $plan,
            $empresa,
        );

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}

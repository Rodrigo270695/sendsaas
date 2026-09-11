<?php

declare(strict_types=1);

namespace App\Support\Plan;

use App\Models\Contact;
use App\Models\Plan;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\TenantWhatsappSession;
use App\Models\User;
use App\Services\Billing\OutboundDailyQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cupos de un plan. -1 en valor_int = ilimitado.
 *
 * Sin facturación ni overrides: el worker de envío y OpenWA leen esto.
 */
final class PlanLimits
{
    /** @var list<string> */
    public const INT_LIMIT_FEATURES = [
        'max_usuarios',
        'max_whatsapp_sessions',
        'max_outbound_per_day',
        'max_contacts',
        'max_campaigns',
        'max_automations',
        'max_sedes',
    ];

    /**
     * @return int|null null = ilimitado o sin plan
     */
    public static function intLimit(?Plan $plan, string $feature): ?int
    {
        if ($plan === null) {
            return null;
        }

        $value = $plan->resolveFeature($feature);

        if (is_numeric($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0) {
            return null;
        }

        return $value;
    }

    public static function moduleEnabled(?Plan $plan, string $feature): bool
    {
        if ($plan === null) {
            return false;
        }

        $meta = Plan::FEATURE_CATALOG[$feature] ?? null;
        if (($meta['type'] ?? null) !== 'bool') {
            return false;
        }

        return (bool) $plan->resolveFeature($feature);
    }

    public static function stringValue(?Plan $plan, string $feature): ?string
    {
        if ($plan === null) {
            return null;
        }

        $value = $plan->resolveFeature($feature);

        return is_string($value) ? $value : null;
    }

    public static function wouldExceed(
        ?Plan $plan,
        string $feature,
        int $used,
        int $adding = 1,
    ): bool {
        $limit = self::intLimit($plan, $feature);

        if ($limit === null) {
            return false;
        }

        return ($used + $adding) > $limit;
    }

    public static function isReached(?Plan $plan, string $feature, int $used): bool
    {
        return self::wouldExceed($plan, $feature, $used, 1);
    }

    public static function message(?Plan $plan, string $feature, ?int $limit = null): string
    {
        $limit ??= self::intLimit($plan, $feature);

        return __('plan.limits.'.$feature, [
            'limit' => $limit ?? 0,
            'plan' => $plan?->nombre ?? __('plan.limits.unknown_plan'),
        ]);
    }

    /**
     * Consumo vs límite para Inertia (botones deshabilitados, barra de cupo).
     *
     * @return array<string, array{limit: int|null, used: int, remaining: int|null, reached: bool, unlimited: bool}>|null
     */
    public static function snapshot(?Tenant $tenant = null): ?array
    {
        $tenant ??= current_tenant();

        if ($tenant === null) {
            return null;
        }

        try {
            $tenant->loadMissing('plan');
            $plan = $tenant->plan;
            $out = [];

            foreach (self::INT_LIMIT_FEATURES as $feature) {
                $limit = self::intLimit($plan, $feature);
                $used = self::currentCount($tenant, $feature);
                $unlimited = $limit === null;

                $out[$feature] = [
                    'limit' => $limit,
                    'used' => $used,
                    'remaining' => $unlimited ? null : max(0, $limit - $used),
                    'reached' => ! $unlimited && $used >= $limit,
                    'unlimited' => $unlimited,
                ];
            }

            return $out;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function currentCount(Tenant $tenant, string $feature): int
    {
        return match ($feature) {
            'max_sedes' => self::countIfTableExists('sedes', fn () => Sede::query()->where('tenant_id', $tenant->id)->count()),
            'max_usuarios' => User::query()->where('tenant_id', $tenant->id)->count(),
            'max_whatsapp_sessions' => self::countIfTableExists(
                'tenant_whatsapp_sessions',
                fn () => TenantWhatsappSession::query()->where('tenant_id', $tenant->id)->count(),
            ),
            'max_contacts' => Schema::hasTable('contacts') ? Contact::query()->count() : 0,
            'max_outbound_per_day' => self::publicTableExists('usage_records')
                ? app(OutboundDailyQuota::class)->usedToday($tenant)
                : 0,
            default => 0,
        };
    }

    /**
     * @param  callable(): int  $count
     */
    private static function countIfTableExists(string $table, callable $count): int
    {
        if (! self::publicTableExists($table)) {
            return 0;
        }

        return $count();
    }

    /**
     * Con search_path de tenant (`od_*`, public), Schema::hasTable()
     * mira el schema de empresa primero y no ve tablas de `public`.
     */
    public static function publicTableExists(string $table): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            return (bool) DB::selectOne(
                'select 1 from information_schema.tables where table_schema = ? and table_name = ? limit 1',
                ['public', $table],
            );
        }

        return Schema::hasTable($table);
    }
}

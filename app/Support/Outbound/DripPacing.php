<?php

declare(strict_types=1);

namespace App\Support\Outbound;

use App\Models\Tenant;
use App\Support\Plan\PlanLimits;
use Illuminate\Support\Facades\Cache;

final class DripPacing
{
    public static function nextAtCacheKey(string $tenantId): string
    {
        return 'openwa:drip:next:'.$tenantId;
    }

    public static function isReady(Tenant $tenant): bool
    {
        $next = Cache::get(self::nextAtCacheKey((string) $tenant->id));
        if (! is_numeric($next)) {
            return true;
        }

        return now()->timestamp >= (int) $next;
    }

    public static function markSent(Tenant $tenant, int $remainingAfterSend): void
    {
        $seconds = self::withJitter(self::intervalSeconds($tenant, $remainingAfterSend));
        Cache::put(
            self::nextAtCacheKey((string) $tenant->id),
            now()->addSeconds($seconds)->timestamp,
            now()->addDay(),
        );
    }

    public static function intervalSeconds(Tenant $tenant, int $remainingQuota): int
    {
        $min = max(1, (int) config('outbound.min_interval_seconds', 60));
        if ($remainingQuota <= 0) {
            return $min;
        }

        $tenant->loadMissing('plan');
        $window = SendWindow::durationSeconds($tenant->plan);

        return max($min, (int) ceil($window / $remainingQuota));
    }

    public static function withJitter(int $seconds): int
    {
        $ratio = (float) config('outbound.jitter', 0.2);
        if ($ratio <= 0) {
            return $seconds;
        }

        $delta = (int) round($seconds * $ratio);
        if ($delta <= 0) {
            return $seconds;
        }

        return max(1, $seconds + random_int(-$delta, $delta));
    }

    public static function hourlyCap(Tenant $tenant): ?int
    {
        $tenant->loadMissing('plan');
        $limit = PlanLimits::intLimit($tenant->plan, 'max_outbound_per_day');
        if ($limit === null || $limit <= 0) {
            return null;
        }

        $hours = max(1, (int) ceil(SendWindow::durationSeconds($tenant->plan) / 3600));

        return (int) ceil($limit / $hours);
    }
}

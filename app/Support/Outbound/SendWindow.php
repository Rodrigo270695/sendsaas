<?php

declare(strict_types=1);

namespace App\Support\Outbound;

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Plan\PlanLimits;
use Illuminate\Support\Carbon;

final class SendWindow
{
    public static function timezone(Tenant $tenant): string
    {
        $tz = trim((string) $tenant->timezone);

        return $tz !== '' ? $tz : (string) config('app.timezone', 'America/Lima');
    }

    /**
     * @return array{start: string, end: string}
     */
    public static function bounds(?Plan $plan): array
    {
        return [
            'start' => PlanLimits::stringValue($plan, 'send_window_start') ?? '08:00',
            'end' => PlanLimits::stringValue($plan, 'send_window_end') ?? '20:00',
        ];
    }

    public static function isOpen(Tenant $tenant, ?Carbon $at = null): bool
    {
        $tenant->loadMissing('plan');
        $tz = self::timezone($tenant);
        $at = ($at ?? now())->copy()->timezone($tz);
        $bounds = self::bounds($tenant->plan);
        $nowMinutes = self::minutesOfDay($at->format('H:i'));
        $start = self::minutesOfDay($bounds['start']);
        $end = self::minutesOfDay($bounds['end']);

        if ($end <= $start) {
            return $nowMinutes >= $start || $nowMinutes < $end;
        }

        return $nowMinutes >= $start && $nowMinutes < $end;
    }

    public static function durationSeconds(?Plan $plan): int
    {
        $bounds = self::bounds($plan);
        $start = self::minutesOfDay($bounds['start']);
        $end = self::minutesOfDay($bounds['end']);
        $minutes = $end > $start ? $end - $start : (24 * 60) - $start + $end;

        return max(60, $minutes * 60);
    }

    private static function minutesOfDay(string $hhmm): int
    {
        $parts = explode(':', $hhmm);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return ($hour * 60) + $minute;
    }
}

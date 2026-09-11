<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Support\Plan\PlanLimits;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class OutboundDailyQuota
{
    public function usedToday(Tenant $tenant): int
    {
        return (int) (UsageRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric', UsageRecord::METRIC_OUTBOUND_DAY)
            ->whereDate('period', $this->periodDate($tenant))
            ->value('used') ?? 0);
    }

    public function wouldExceed(Tenant $tenant, int $adding = 1): bool
    {
        $tenant->loadMissing('plan');

        return PlanLimits::wouldExceed(
            $tenant->plan,
            'max_outbound_per_day',
            $this->usedToday($tenant),
            $adding,
        );
    }

    public function increment(Tenant $tenant, int $by = 1): void
    {
        $period = $this->periodDate($tenant);

        DB::transaction(function () use ($tenant, $period, $by): void {
            $row = UsageRecord::query()
                ->where('tenant_id', $tenant->id)
                ->where('metric', UsageRecord::METRIC_OUTBOUND_DAY)
                ->whereDate('period', $period)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                UsageRecord::query()->create([
                    'tenant_id' => $tenant->id,
                    'metric' => UsageRecord::METRIC_OUTBOUND_DAY,
                    'period' => $period,
                    'used' => $by,
                ]);

                return;
            }

            $row->increment('used', $by);
        });
    }

    public function periodDate(Tenant $tenant): string
    {
        $tz = (string) ($tenant->timezone ?: config('app.timezone', 'America/Lima'));

        return Carbon::now($tz)->toDateString();
    }
}

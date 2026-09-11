<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $metric
 * @property Carbon $period
 * @property int $used
 */
class UsageRecord extends Model
{
    use HasUuids;
    use UsesPublicSchema;

    public const METRIC_OUTBOUND_DAY = 'outbound_day';

    protected $table = 'usage_records';

    protected $fillable = [
        'tenant_id',
        'metric',
        'period',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'used' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

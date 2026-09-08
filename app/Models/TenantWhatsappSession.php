<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Database\Factories\TenantWhatsappSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property ?string $sede_id
 * @property string $alias
 * @property ?string $openwa_session_id
 * @property string $openwa_session_name
 * @property string $status
 * @property ?string $phone
 * @property ?string $push_name
 * @property bool $auto_reconnect
 */
class TenantWhatsappSession extends Model
{
    /** @use HasFactory<TenantWhatsappSessionFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use UsesPublicSchema;

    public const STATUSES = [
        'created',
        'initializing',
        'qr_ready',
        'authenticating',
        'ready',
        'disconnected',
        'failed',
    ];

    public const STATUS_CONNECTED = 'ready';

    /** @var list<string> */
    public const STATUSES_PENDING = [
        'created',
        'initializing',
        'qr_ready',
        'authenticating',
    ];

    /** @var list<string> */
    public const STATUSES_DISCONNECTED = [
        'disconnected',
        'failed',
    ];

    protected $table = 'tenant_whatsapp_sessions';

    protected $fillable = [
        'tenant_id',
        'sede_id',
        'alias',
        'openwa_session_id',
        'openwa_session_name',
        'status',
        'phone',
        'push_name',
        'connected_at',
        'last_synced_at',
        'last_error',
        'auto_reconnect',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'auto_reconnect' => 'boolean',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public static function generateSessionName(string $tenantId, string $slug): string
    {
        // Prefijo para no chocar con VetSaaS si comparten el mismo OpenWA (`demo`).
        $base = 'ss-'.self::sanitizeSessionSlug($slug);
        $existing = self::withTrashed()
            ->where('tenant_id', $tenantId)
            ->pluck('openwa_session_name')
            ->all();

        if (! in_array($base, $existing, true)) {
            return $base;
        }

        $n = 2;
        while (in_array($base.'-'.$n, $existing, true)) {
            $n++;
        }

        return $base.'-'.$n;
    }

    public static function sanitizeSessionSlug(string $slug): string
    {
        $clean = strtolower(trim($slug));
        $clean = preg_replace('/[^a-z0-9\-]+/', '-', $clean) ?? $clean;
        $clean = trim($clean, '-');

        return $clean !== '' ? $clean : 'sesion';
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function statusGroup(): string
    {
        if ($this->status === self::STATUS_CONNECTED) {
            return 'conectada';
        }

        if (in_array($this->status, self::STATUSES_DISCONNECTED, true)) {
            return 'desconectada';
        }

        return 'pendiente';
    }
}

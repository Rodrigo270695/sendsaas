<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use App\Tenancy\TenantManager;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids, SoftDeletes, UsesPublicSchema;

    public const ESTADOS = ['trial', 'active', 'suspended', 'cancelled'];

    protected $fillable = [
        'slug',
        'schema_name',
        'plan_id',
        'razon_social',
        'nombre_comercial',
        'ruc',
        'email_admin',
        'telefono',
        'direccion',
        'logo_url',
        'estado',
        'trial_ends_at',
        'suspended_at',
        'suspension_reason',
        'cancelled_at',
        'cancel_reason',
        'timezone',
        'locale',
        'canal_adquisicion',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'onboarding_completado' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Sede, $this>
     */
    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    /**
     * @return HasMany<TenantWhatsappSession, $this>
     */
    public function whatsappSessions(): HasMany
    {
        return $this->hasMany(TenantWhatsappSession::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public static function schemaFromSlug(string $slug): string
    {
        return TenantManager::schemaFromSlug($slug);
    }
}

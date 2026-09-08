<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    public const CODIGO_FREE = 'free';

    /** Meses que se cobran al pagar anual (2 de regalo). */
    public const MESES_ANUAL_COBRADOS = 10;

    /**
     * @var list<string>
     */
    public const CODIGOS_CATALOGO = [
        'free',
        'starter',
        'profesional',
        'business',
        'enterprise',
    ];

    /**
     * Features conocidas. Fuente de verdad del UI y de `resolveFeature`.
     * Cupos de uso + ventana de envío. Sin módulos ni facturación.
     *
     * @var array<string, array{type: 'int'|'bool'|'str', group: string, default: int|bool|string|null}>
     */
    public const FEATURE_CATALOG = [
        'max_usuarios' => ['type' => 'int', 'group' => 'limites', 'default' => 2],
        'max_whatsapp_sessions' => ['type' => 'int', 'group' => 'limites', 'default' => 1],
        'max_outbound_per_day' => ['type' => 'int', 'group' => 'limites', 'default' => 500],
        'max_contacts' => ['type' => 'int', 'group' => 'limites', 'default' => 500],
        'max_campaigns' => ['type' => 'int', 'group' => 'limites', 'default' => 5],
        'max_automations' => ['type' => 'int', 'group' => 'limites', 'default' => 3],
        'max_sedes' => ['type' => 'int', 'group' => 'limites', 'default' => 1],

        'send_window_start' => ['type' => 'str', 'group' => 'envio', 'default' => '08:00'],
        'send_window_end' => ['type' => 'str', 'group' => 'envio', 'default' => '20:00'],
        'soporte_tipo' => ['type' => 'str', 'group' => 'envio', 'default' => 'email'],
    ];

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'badge',
        'color_hex',
        'precio_mensual',
        'precio_anual',
        'trial_days',
        'orden',
        'es_publico',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'precio_anual' => 'decimal:2',
            'trial_days' => 'integer',
            'es_publico' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function isFree(): bool
    {
        return $this->codigo === self::CODIGO_FREE;
    }

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeExcludingFree(Builder $query): Builder
    {
        return $query->where('codigo', '!=', self::CODIGO_FREE);
    }

    public static function findByCodigo(string $codigo): ?self
    {
        return self::query()->where('codigo', $codigo)->first();
    }

    public static function precioAnualDesdeMensual(float|int|string $mensual): string
    {
        return number_format(
            round(((float) $mensual) * self::MESES_ANUAL_COBRADOS, 2),
            2,
            '.',
            '',
        );
    }

    /**
     * Valor efectivo. Si no hay fila, usa el default del catálogo.
     */
    public function resolveFeature(string $feature): int|bool|string|null
    {
        $meta = self::FEATURE_CATALOG[$feature] ?? null;
        if (! $meta) {
            return null;
        }

        /** @var PlanFeature|null $row */
        $row = $this->relationLoaded('features')
            ? $this->features->firstWhere('feature', $feature)
            : $this->features()->where('feature', $feature)->first();

        if (! $row) {
            return $meta['default'];
        }

        return match ($meta['type']) {
            'int' => $row->valor_int !== null ? (int) $row->valor_int : null,
            'bool' => $row->valor_bool,
            'str' => $row->valor_str,
        };
    }
}

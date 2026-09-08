<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Database\Factories\SedeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $nombre
 * @property string $codigo
 * @property ?string $direccion
 * @property ?string $telefono
 * @property ?string $email
 * @property ?int $distrito_id
 * @property ?string $distrito
 * @property ?string $provincia
 * @property ?string $departamento
 * @property bool $activa
 */
class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use UsesPublicSchema;

    protected $table = 'sedes';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'email',
        'distrito_id',
        'distrito',
        'provincia',
        'departamento',
        'activa',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
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

    /**
     * @return BelongsTo<Distrito, $this>
     */
    public function distritoModel(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    public static function generateNextCode(string $tenantId): string
    {
        $maxNumber = self::withTrashed()
            ->where('tenant_id', $tenantId)
            ->pluck('codigo')
            ->map(fn ($c) => (int) preg_replace('/\D/', '', (string) $c))
            ->max() ?? 0;

        return 'SEDE-'.str_pad((string) ($maxNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}

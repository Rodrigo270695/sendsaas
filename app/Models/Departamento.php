<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use UsesPublicSchema;

    protected $table = 'departamentos';

    protected $fillable = [
        'pais_id',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pais_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Pais, $this>
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    /**
     * @return HasMany<Provincia, $this>
     */
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class);
    }
}

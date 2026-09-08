<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    use UsesPublicSchema;

    protected $table = 'provincias';

    protected $fillable = [
        'departamento_id',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'departamento_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Departamento, $this>
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * @return HasMany<Distrito, $this>
     */
    public function distritos(): HasMany
    {
        return $this->hasMany(Distrito::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    use UsesPublicSchema;

    protected $table = 'paises';

    protected $fillable = [
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Departamento, $this>
     */
    public function departamentos(): HasMany
    {
        return $this->hasMany(Departamento::class);
    }
}

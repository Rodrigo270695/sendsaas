<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distrito extends Model
{
    use UsesPublicSchema;

    protected $table = 'distritos';

    protected $fillable = [
        'provincia_id',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'provincia_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Provincia, $this>
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }
}

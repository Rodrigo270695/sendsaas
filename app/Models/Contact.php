<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $phone
 * @property ?string $email
 * @property ?string $notes
 * @property ?string $sede_id
 * @property array<string, string>|null $custom_fields
 * @property ?Carbon $last_contact_at
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'contacts';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'notes',
        'sede_id',
        'custom_fields',
        'last_contact_at',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'last_contact_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tags')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

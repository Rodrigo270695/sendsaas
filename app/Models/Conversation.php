<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $contact_id
 * @property string $channel
 * @property ?string $whatsapp_session_id
 * @property ?string $wa_chat_id
 * @property ?string $assigned_user_id
 * @property ?string $sede_id
 * @property string $status
 * @property string $priority
 * @property ?Carbon $last_message_at
 * @property int $unread_count
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use HasUuids;

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_CLOSED = 'CLOSED';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PENDING,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    protected $table = 'conversations';

    protected $fillable = [
        'contact_id',
        'channel',
        'whatsapp_session_id',
        'wa_chat_id',
        'assigned_user_id',
        'sede_id',
        'status',
        'priority',
        'last_message_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latest('created_at');
    }

    /**
     * @return BelongsTo<TenantWhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(TenantWhatsappSession::class, 'whatsapp_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'conversation_tags')->withTimestamps();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OutboundQueueItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kind
 * @property string $priority
 * @property string $status
 * @property ?string $conversation_id
 * @property ?string $contact_id
 * @property ?string $whatsapp_session_id
 * @property ?string $campaign_id
 * @property string $contact_phone
 * @property string $chat_id
 * @property string $message_type
 * @property string $body
 * @property array<string, mixed>|null $payload
 * @property Carbon $available_at
 * @property ?Carbon $reserved_at
 * @property ?Carbon $sent_at
 * @property int $attempts
 * @property ?string $last_error
 * @property ?string $created_by_id
 */
class OutboundQueueItem extends Model
{
    /** @use HasFactory<OutboundQueueItemFactory> */
    use HasFactory;

    use HasUuids;

    public const KIND_CAMPAIGN = 'campaign';

    public const KIND_REMINDER = 'reminder';

    public const KIND_AUTOMATION = 'automation';

    public const KIND_MANUAL = 'manual';

    public const PRIORITY_DRIP = 'drip';

    public const PRIORITY_INTERACTIVE = 'interactive';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_CAMPAIGN,
        self::KIND_REMINDER,
        self::KIND_AUTOMATION,
        self::KIND_MANUAL,
    ];

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_RESERVED,
        self::STATUS_SENT,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'outbound_queue';

    protected $fillable = [
        'kind',
        'priority',
        'status',
        'conversation_id',
        'contact_id',
        'whatsapp_session_id',
        'campaign_id',
        'contact_phone',
        'chat_id',
        'message_type',
        'body',
        'payload',
        'available_at',
        'reserved_at',
        'sent_at',
        'attempts',
        'last_error',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'reserved_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}

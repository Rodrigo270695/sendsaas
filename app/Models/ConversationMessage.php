<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_type
 * @property ?string $sender_id
 * @property string $external_id
 * @property string $direction
 * @property string $message_type
 * @property ?string $body
 * @property ?string $media_url
 * @property string $status
 * @property ?Carbon $sent_at
 * @property array<string, mixed>|null $metadata
 */
class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    use HasUuids;

    public const SENDER_CONTACT = 'contact';

    public const SENDER_AGENT = 'agent';

    public const SENDER_SYSTEM = 'system';

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'conversation_messages';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'external_id',
        'direction',
        'message_type',
        'body',
        'media_url',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}

<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\OutboundQueueItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboundQueueItem>
 */
class OutboundQueueItemFactory extends Factory
{
    protected $model = OutboundQueueItem::class;

    public function definition(): array
    {
        $phone = '519'.fake()->numerify('########');

        return [
            'kind' => OutboundQueueItem::KIND_MANUAL,
            'priority' => OutboundQueueItem::PRIORITY_DRIP,
            'status' => OutboundQueueItem::STATUS_QUEUED,
            'conversation_id' => null,
            'contact_id' => Contact::factory(),
            'whatsapp_session_id' => null,
            'campaign_id' => null,
            'contact_phone' => $phone,
            'chat_id' => $phone.'@c.us',
            'message_type' => 'text',
            'body' => fake()->sentence(),
            'payload' => null,
            'available_at' => now()->subMinute(),
            'reserved_at' => null,
            'sent_at' => null,
            'attempts' => 0,
            'last_error' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => OutboundQueueItem::STATUS_QUEUED,
            'available_at' => now()->subMinute(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => OutboundQueueItem::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }
}

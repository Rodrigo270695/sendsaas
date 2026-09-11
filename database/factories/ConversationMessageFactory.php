<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    protected $model = ConversationMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_type' => ConversationMessage::SENDER_CONTACT,
            'sender_id' => null,
            'external_id' => 'wamid.'.fake()->unique()->uuid(),
            'direction' => ConversationMessage::DIRECTION_IN,
            'message_type' => 'text',
            'body' => fake()->sentence(),
            'media_url' => null,
            'status' => ConversationMessage::STATUS_RECEIVED,
            'sent_at' => now(),
            'metadata' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'channel' => Conversation::CHANNEL_WHATSAPP,
            'whatsapp_session_id' => null,
            'wa_chat_id' => null,
            'assigned_user_id' => null,
            'sede_id' => null,
            'status' => Conversation::STATUS_OPEN,
            'priority' => 'normal',
            'last_message_at' => now(),
            'unread_count' => 0,
        ];
    }
}

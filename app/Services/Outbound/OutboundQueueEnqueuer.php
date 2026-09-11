<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\OutboundQueueItem;
use App\Support\WhatsApp\WhatsAppPhone;
use InvalidArgumentException;

final class OutboundQueueEnqueuer
{
    /**
     * @param  array{
     *     kind?: string,
     *     priority?: string,
     *     conversation_id?: string|null,
     *     contact_id?: string|null,
     *     whatsapp_session_id?: string|null,
     *     campaign_id?: string|null,
     *     contact_phone?: string|null,
     *     chat_id?: string|null,
     *     message_type?: string,
     *     body: string,
     *     payload?: array<string, mixed>|null,
     *     available_at?: \DateTimeInterface|null,
     *     created_by_id?: string|null
     * }  $data
     */
    public function enqueue(array $data): OutboundQueueItem
    {
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            throw new InvalidArgumentException('El mensaje de la cola no puede estar vacío.');
        }

        $phone = WhatsAppPhone::normalize((string) ($data['contact_phone'] ?? ''));
        $chatId = trim((string) ($data['chat_id'] ?? ''));

        if ($phone === null && isset($data['contact_id'])) {
            $contact = Contact::query()->whereKey($data['contact_id'])->first();
            $phone = WhatsAppPhone::normalize($contact?->phone);
        }

        if ($chatId === '' && $phone !== null) {
            $chatId = $phone.'@c.us';
        }

        if ($phone === null || $chatId === '') {
            throw new InvalidArgumentException('La cola necesita un teléfono o chat de WhatsApp.');
        }

        $kind = (string) ($data['kind'] ?? OutboundQueueItem::KIND_MANUAL);
        if (! in_array($kind, OutboundQueueItem::KINDS, true)) {
            throw new InvalidArgumentException('Tipo de envío no válido: '.$kind);
        }

        return OutboundQueueItem::query()->create([
            'kind' => $kind,
            'priority' => $data['priority'] ?? OutboundQueueItem::PRIORITY_DRIP,
            'status' => OutboundQueueItem::STATUS_QUEUED,
            'conversation_id' => $data['conversation_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'whatsapp_session_id' => $data['whatsapp_session_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'contact_phone' => $phone,
            'chat_id' => $chatId,
            'message_type' => $data['message_type'] ?? 'text',
            'body' => $body,
            'payload' => $data['payload'] ?? null,
            'available_at' => $data['available_at'] ?? now(),
            'created_by_id' => $data['created_by_id'] ?? null,
        ]);
    }

    public function enqueueForContact(
        Contact $contact,
        string $body,
        string $kind = OutboundQueueItem::KIND_MANUAL,
        ?Conversation $conversation = null,
    ): OutboundQueueItem {
        return $this->enqueue([
            'kind' => $kind,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'contact_phone' => $contact->phone,
            'chat_id' => $conversation?->wa_chat_id,
            'body' => $body,
        ]);
    }
}

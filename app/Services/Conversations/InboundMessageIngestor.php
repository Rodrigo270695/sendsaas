<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TenantWhatsappSession;
use App\Support\OpenWa\OpenWaInboundPayload;
use App\Support\WhatsApp\WhatsAppPhone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class InboundMessageIngestor
{
    /**
     * @return array{
     *     ingested: bool,
     *     duplicate: bool,
     *     conversation_id: string,
     *     message_id: string,
     *     contact_id: string
     * }
     */
    public function ingest(OpenWaInboundPayload $payload, ?TenantWhatsappSession $session): array
    {
        $existing = ConversationMessage::query()
            ->where('external_id', $payload->externalId)
            ->first();

        if ($existing !== null) {
            return [
                'ingested' => false,
                'duplicate' => true,
                'conversation_id' => $existing->conversation_id,
                'message_id' => $existing->id,
                'contact_id' => (string) $existing->conversation()->value('contact_id'),
            ];
        }

        try {
            return DB::transaction(fn (): array => $this->store($payload, $session));
        } catch (QueryException $e) {
            $existing = ConversationMessage::query()
                ->where('external_id', $payload->externalId)
                ->first();

            if ($existing !== null) {
                return [
                    'ingested' => false,
                    'duplicate' => true,
                    'conversation_id' => $existing->conversation_id,
                    'message_id' => $existing->id,
                    'contact_id' => (string) $existing->conversation()->value('contact_id'),
                ];
            }

            throw $e;
        }
    }

    /**
     * @return array{
     *     ingested: bool,
     *     duplicate: bool,
     *     conversation_id: string,
     *     message_id: string,
     *     contact_id: string
     * }
     */
    private function store(OpenWaInboundPayload $payload, ?TenantWhatsappSession $session): array
    {
        $phone = $payload->phone;
        if ($phone === null) {
            throw new \InvalidArgumentException('El payload no tiene un teléfono resoluble.');
        }

        $contact = $this->upsertContact($phone, $payload->name, $session?->sede_id);
        $conversation = $this->upsertConversation($contact, $payload, $session);

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => ConversationMessage::SENDER_CONTACT,
            'sender_id' => $contact->id,
            'external_id' => $payload->externalId,
            'direction' => ConversationMessage::DIRECTION_IN,
            'message_type' => $payload->messageType,
            'body' => $payload->body !== '' ? $payload->body : null,
            'media_url' => $payload->mediaUrl,
            'status' => ConversationMessage::STATUS_RECEIVED,
            'sent_at' => now(),
            'metadata' => [
                'wa_chat_id' => $payload->waChatId,
                'openwa_session_id' => $payload->sessionId !== '' ? $payload->sessionId : null,
                'openwa_type' => $payload->raw['type'] ?? null,
            ],
        ]);

        return [
            'ingested' => true,
            'duplicate' => false,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
        ];
    }

    private function upsertContact(string $phone, ?string $name, ?string $sedeId): Contact
    {
        $contact = Contact::query()->where('phone', $phone)->first();
        $resolvedName = $this->resolveName($name, $phone, $contact?->name);

        if ($contact === null) {
            return Contact::query()->create([
                'name' => $resolvedName,
                'phone' => $phone,
                'sede_id' => $sedeId,
                'last_contact_at' => now(),
            ]);
        }

        $contact->forceFill([
            'name' => $resolvedName,
            'last_contact_at' => now(),
        ])->save();

        return $contact;
    }

    private function upsertConversation(
        Contact $contact,
        OpenWaInboundPayload $payload,
        ?TenantWhatsappSession $session,
    ): Conversation {
        $conversation = Conversation::query()
            ->where('contact_id', $contact->id)
            ->where('channel', Conversation::CHANNEL_WHATSAPP)
            ->latest('last_message_at')
            ->first();

        $attributes = [
            'channel' => Conversation::CHANNEL_WHATSAPP,
            'whatsapp_session_id' => $session?->id,
            'wa_chat_id' => $payload->waChatId !== '' ? $payload->waChatId : $conversation?->wa_chat_id,
            'sede_id' => $session?->sede_id ?? $contact->sede_id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
        ];

        if ($conversation === null) {
            return Conversation::query()->create([
                ...$attributes,
                'contact_id' => $contact->id,
                'unread_count' => 1,
            ]);
        }

        $conversation->forceFill([
            ...$attributes,
            'unread_count' => $conversation->unread_count + 1,
        ])->save();

        return $conversation;
    }

    private function resolveName(?string $incoming, string $phone, ?string $current): string
    {
        $incoming = $incoming !== null ? trim($incoming) : '';
        $fallback = WhatsAppPhone::formatDisplay($phone);
        $fallback = $fallback !== '' ? $fallback : $phone;

        if ($current === null || $current === '' || $current === $phone || $current === $fallback) {
            return $incoming !== '' ? $incoming : $fallback;
        }

        return $current;
    }
}

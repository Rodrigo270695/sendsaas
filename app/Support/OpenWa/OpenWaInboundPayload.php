<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

use App\Support\WhatsApp\WhatsAppPhone;

final class OpenWaInboundPayload
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $event,
        public readonly bool $fromMe,
        public readonly string $sessionId,
        public readonly string $externalId,
        public readonly string $waChatId,
        public readonly ?string $phone,
        public readonly ?string $name,
        public readonly string $body,
        public readonly string $messageType,
        public readonly ?string $mediaUrl,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $event = (string) ($payload['event'] ?? $payload['type'] ?? $data['event'] ?? '');
        $fromMe = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);

        $from = (string) ($data['from'] ?? '');
        $chatId = (string) ($data['chatId'] ?? $data['chat_id'] ?? '');
        $waChatId = $from !== '' ? $from : $chatId;
        $phone = self::extractPhone($data, $waChatId, $chatId);

        $externalId = trim((string) ($data['id'] ?? $data['messageId'] ?? $data['message_id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'ow:'.hash('sha256', (string) json_encode([
                $payload['sessionId'] ?? '',
                $waChatId,
                $data['body'] ?? '',
                $data['timestamp'] ?? '',
            ]));
        }

        return new self(
            event: $event,
            fromMe: $fromMe,
            sessionId: (string) ($payload['sessionId'] ?? $data['sessionId'] ?? ''),
            externalId: $externalId,
            waChatId: $waChatId,
            phone: $phone,
            name: self::extractName($data),
            body: trim((string) ($data['body'] ?? $data['content'] ?? $data['text'] ?? $data['caption'] ?? '')),
            messageType: self::mapMessageType((string) ($data['type'] ?? $data['messageType'] ?? 'chat')),
            mediaUrl: self::extractMediaUrl($data),
            raw: $data,
        );
    }

    public function isGroup(): bool
    {
        return str_ends_with($this->waChatId, '@g.us');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractPhone(array $data, string $waChatId, string $chatId): ?string
    {
        $sender = is_array($data['sender'] ?? null) ? $data['sender'] : [];
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];

        $candidates = [
            $data['senderPn'] ?? null,
            $data['fromPn'] ?? null,
            $data['participantPn'] ?? null,
            $data['author'] ?? null,
            $data['participant'] ?? null,
            $data['remoteJid'] ?? null,
            $data['remoteJidAlt'] ?? null,
            $chatId,
            $data['from'] ?? null,
            $sender['id'] ?? null,
            $sender['number'] ?? null,
            $contact['number'] ?? null,
            $contact['id'] ?? null,
            $waChatId,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $phone = self::phoneFromChatId($candidate);
            if ($phone !== null) {
                return $phone;
            }
        }

        foreach ([$chatId, $waChatId, (string) ($data['from'] ?? '')] as $candidate) {
            $lid = self::phoneFromLid($candidate);
            if ($lid !== null) {
                return $lid;
            }
        }

        return null;
    }

    private static function phoneFromChatId(string $chatId): ?string
    {
        if ($chatId === '' || str_ends_with($chatId, '@lid') || str_ends_with($chatId, '@g.us')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', preg_replace('/@(c\.us|s\.whatsapp\.net)$/', '', $chatId) ?? $chatId) ?? '';

        return WhatsAppPhone::normalize($digits);
    }

    private static function phoneFromLid(string $chatId): ?string
    {
        if (! str_ends_with($chatId, '@lid')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $chatId) ?? '';
        if ($digits === '') {
            return null;
        }

        return 'lid:'.substr($digits, -16);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractName(array $data): ?string
    {
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
        $sender = is_array($data['sender'] ?? null) ? $data['sender'] : [];

        foreach ([
            $contact['name'] ?? null,
            $contact['pushName'] ?? null,
            $contact['pushname'] ?? null,
            $sender['name'] ?? null,
            $sender['pushName'] ?? null,
            $sender['pushname'] ?? null,
            $data['pushName'] ?? null,
            $data['notifyName'] ?? null,
            $data['name'] ?? null,
        ] as $name) {
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractMediaUrl(array $data): ?string
    {
        $direct = $data['mediaUrl'] ?? $data['media_url'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $media = is_array($data['media'] ?? null) ? $data['media'] : [];
        $url = $media['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    private static function mapMessageType(string $raw): string
    {
        $type = strtolower(trim($raw));

        return match (true) {
            in_array($type, ['chat', 'text', 'conversation'], true) => 'text',
            in_array($type, ['image', 'sticker'], true) => 'image',
            in_array($type, ['document', 'documentwithcaption'], true) => 'document',
            $type === 'video' => 'video',
            in_array($type, ['audio', 'ptt'], true) => 'audio',
            $type === 'location' => 'location',
            default => $type !== '' ? $type : 'text',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

final class WhatsAppPhone
{
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '51'.$digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    public static function formatDisplay(?string $phone): string
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return '';
        }

        if (str_starts_with($normalized, '51') && strlen($normalized) === 11) {
            return '+51 '.substr($normalized, 2, 3).' '.substr($normalized, 5, 3).' '.substr($normalized, 8);
        }

        return '+'.$normalized;
    }
}

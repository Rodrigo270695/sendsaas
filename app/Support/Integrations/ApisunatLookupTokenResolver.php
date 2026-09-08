<?php

declare(strict_types=1);

namespace App\Support\Integrations;

final class ApisunatLookupTokenResolver
{
    public static function resolve(): ?string
    {
        $token = trim((string) config('services.apisunat_lookup.token', ''));

        return $token !== '' ? $token : null;
    }
}

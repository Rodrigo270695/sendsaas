<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Http\Request;

final class TenantImpersonationAcceptUrl
{
    public static function build(Tenant $tenant, string $token, Request $request): string
    {
        $slug = trim((string) $tenant->slug);
        $root = trim((string) config('tenant.root_domain'));

        $scheme = $request->getScheme();
        $appScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        if (is_string($appScheme) && $appScheme === 'https') {
            $scheme = 'https';
        }

        $host = $slug.'.'.$root;

        $port = $request->getPort();
        $authority = $host;
        if ($port !== null && ! in_array((int) $port, [80, 443], true)) {
            $authority .= ':'.$port;
        }

        return $scheme.'://'.$authority.'/impersonate/accept?token='.rawurlencode($token);
    }
}

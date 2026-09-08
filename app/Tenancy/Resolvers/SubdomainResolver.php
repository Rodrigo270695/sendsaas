<?php

declare(strict_types=1);

namespace App\Tenancy\Resolvers;

use Illuminate\Http\Request;

class SubdomainResolver
{
    public function resolveFromRequest(Request $request): ?string
    {
        return $this->resolveFromHost($request->getHost());
    }

    public function resolveFromHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        $centrals = array_map('strtolower', (array) config('tenant.central_domains', []));
        if (in_array($host, $centrals, true)) {
            return null;
        }

        $root = strtolower((string) config('tenant.root_domain', ''));
        if ($root === '') {
            return null;
        }

        $suffix = '.'.$root;
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $sub = substr($host, 0, -strlen($suffix));

        if ($sub === '' || str_contains($sub, '.')) {
            return null;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $sub)) {
            return null;
        }

        return $sub;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el team de Spatie al tenant del request (null en plataforma).
 */
final class SetPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        setPermissionsTeamId(tenant_id());

        try {
            return $next($request);
        } finally {
            setPermissionsTeamId(null);
        }
    }
}

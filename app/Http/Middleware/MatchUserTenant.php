<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MatchUserTenant
{
    public function __construct(protected TenantManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            return $next($request);
        }

        $hostTenantId = $this->manager->check() ? $this->manager->id() : null;
        $userTenantId = $user instanceof User ? $user->tenant_id : null;

        if ($hostTenantId !== null
            && $userTenantId === null
            && $user instanceof User
            && $user->isPlatformSuperadmin()) {
            $imp = $request->session()->get('tenant_impersonation');
            if (is_array($imp)
                && isset($imp['tenant_id'])
                && (string) $imp['tenant_id'] === (string) $hostTenantId) {
                return $next($request);
            }
        }

        if ($hostTenantId === $userTenantId) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort(403, $this->buildMessage($hostTenantId, $userTenantId));
    }

    private function buildMessage(?string $hostTenantId, ?string $userTenantId): string
    {
        if ($hostTenantId === null && $userTenantId !== null) {
            return 'Tu cuenta pertenece a una empresa. Inicia sesión desde su subdominio.';
        }

        if ($hostTenantId !== null && $userTenantId === null) {
            return 'Este es el panel de una empresa. Tu cuenta es del panel central de SendSaaS.';
        }

        return 'Tu cuenta no tiene acceso a esta empresa.';
    }
}

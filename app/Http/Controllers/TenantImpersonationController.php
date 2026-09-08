<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantImpersonationAcceptUrl;
use App\Support\Tenancy\TenantImpersonationCentralUrl;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TenantImpersonationController extends Controller
{
    private const CACHE_PREFIX = 'tenant_impersonate:';

    private const CACHE_TTL_SECONDS = 300;

    public function start(Request $request, Tenant $tenant): Response
    {
        abort_unless($request->user()?->can('plataforma-tenants.impersonate'), 403);
        abort_unless($request->user()?->isPlatformSuperadmin(), 403);

        $allowed = (array) config('tenant.allowed_states', ['active', 'trial', 'grace']);
        if (! in_array($tenant->estado, $allowed, true)) {
            throw ValidationException::withMessages([
                'tenant' => 'Solo puedes entrar a empresas activas o en prueba.',
            ]);
        }

        if (trim((string) $tenant->slug) === '') {
            throw ValidationException::withMessages([
                'tenant' => 'La empresa no tiene slug.',
            ]);
        }

        $token = Str::random(64);
        Cache::put(
            self::CACHE_PREFIX.$token,
            [
                'superadmin_id' => (string) $request->user()->id,
                'tenant_id' => (string) $tenant->getKey(),
                'central_origin' => TenantImpersonationCentralUrl::originFromRequest($request),
            ],
            now()->addSeconds(self::CACHE_TTL_SECONDS),
        );

        return Inertia::location(TenantImpersonationAcceptUrl::build($tenant, $token, $request));
    }

    public function accept(Request $request, TenantManager $manager): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        if ($token === '' || strlen($token) < 32) {
            abort(404);
        }

        /** @var array{superadmin_id?: string, tenant_id?: string, central_origin?: string}|null $payload */
        $payload = Cache::get(self::CACHE_PREFIX.$token);

        if (! is_array($payload) || empty($payload['superadmin_id']) || empty($payload['tenant_id'])) {
            return redirect('/login')->with('error', 'El enlace de soporte expiró. Inténtalo de nuevo.');
        }

        $currentTenantId = $manager->check() ? $manager->id() : null;
        if ($currentTenantId === null || (string) $currentTenantId !== (string) $payload['tenant_id']) {
            abort(404);
        }

        /** @var User|null $superadmin */
        $superadmin = User::query()->whereKey($payload['superadmin_id'])->first();

        if ($superadmin === null || ! $superadmin->isPlatformSuperadmin()) {
            abort(403);
        }

        Cache::forget(self::CACHE_PREFIX.$token);

        Auth::guard('web')->login($superadmin);
        $request->session()->regenerate();

        $tenantModel = $manager->current()?->tenant
            ?? Tenant::query()->whereKey($payload['tenant_id'])->firstOrFail();

        $label = trim((string) ($tenantModel->nombre_comercial ?: $tenantModel->razon_social));
        $centralOrigin = isset($payload['central_origin']) && is_string($payload['central_origin'])
            ? trim($payload['central_origin'])
            : '';

        $request->session()->put('tenant_impersonation', [
            'tenant_id' => (string) $payload['tenant_id'],
            'tenant_label' => $label,
            'started_at' => now()->toIso8601String(),
            'central_origin' => $centralOrigin !== '' ? $centralOrigin : null,
        ]);

        return redirect('/dashboard')->with('success', 'Entraste como soporte.');
    }

    public function leave(Request $request): Response|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        abort_unless($user instanceof User && $user->isPlatformSuperadmin(), 403);

        $session = $request->session();
        /** @var array{tenant_id?: string, central_origin?: string|null}|null $imp */
        $imp = $session->get('tenant_impersonation');

        if (! is_array($imp) || empty($imp['tenant_id'])) {
            return redirect()->route('dashboard');
        }

        $centralOrigin = isset($imp['central_origin']) && is_string($imp['central_origin'])
            ? trim($imp['central_origin'])
            : '';

        $returnToken = Str::random(64);
        Cache::put(
            self::CACHE_PREFIX.'return:'.$returnToken,
            [
                'superadmin_id' => (string) $user->id,
            ],
            now()->addSeconds(self::CACHE_TTL_SECONDS),
        );

        $returnUrl = $centralOrigin !== ''
            ? TenantImpersonationCentralUrl::returnUrl($centralOrigin, $returnToken)
            : TenantImpersonationCentralUrl::returnUrl(
                TenantImpersonationCentralUrl::fallbackOrigin($request),
                $returnToken,
            );

        $session->forget('tenant_impersonation');

        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();

        return Inertia::location($returnUrl);
    }

    public function returnToCentral(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        if ($token === '' || strlen($token) < 32) {
            abort(404);
        }

        if (app(TenantManager::class)->check()) {
            abort(404);
        }

        /** @var array{superadmin_id?: string}|null $payload */
        $payload = Cache::pull(self::CACHE_PREFIX.'return:'.$token);

        if (! is_array($payload) || empty($payload['superadmin_id'])) {
            return redirect()->route('login')->with('error', 'La sesión de soporte expiró. Entra de nuevo.');
        }

        /** @var User|null $superadmin */
        $superadmin = User::query()->whereKey($payload['superadmin_id'])->first();

        if ($superadmin === null || ! $superadmin->isPlatformSuperadmin()) {
            abort(403);
        }

        Auth::guard('web')->login($superadmin);
        $request->session()->regenerate();
        $request->session()->forget('tenant_impersonation');

        return redirect()
            ->route('plataforma.tenants.index')
            ->with('success', 'Saliste del modo soporte.');
    }
}

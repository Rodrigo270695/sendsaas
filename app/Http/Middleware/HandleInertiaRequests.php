<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Plan\PlanLimits;
use App\Support\Tenancy\DemoTenant;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'greeting_name' => $this->greetingName($request),
            'tenant' => $this->sharedTenant(),
            'tenancy' => [
                'root_domain' => (string) config('tenant.root_domain'),
                'scheme' => $request->getScheme(),
                'login_path' => '/login',
            ],
            'tenant_impersonation' => fn () => $this->sharedImpersonation($request),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $this->resolveUserPermissions($request->user()),
                'roles' => $this->resolveUserRoles($request->user()),
            ],
            'locale' => $request->getLocale(),
            'contact_whatsapp' => (string) config('app.contact_whatsapp', '51976809804'),
            'plan_limits' => fn () => $request->user() ? PlanLimits::snapshot() : null,
            'timezone' => config('app.timezone'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => function () use ($request) {
                $session = $request->session();
                $payload = [
                    'success' => $session->get('success'),
                    'error' => $session->get('error'),
                    'info' => $session->get('info'),
                    'warning' => $session->get('warning'),
                ];

                $hasMessage = collect($payload)
                    ->filter(fn ($value) => is_string($value) && $value !== '')
                    ->isNotEmpty();

                if (! $hasMessage) {
                    return null;
                }

                return [
                    'id' => sha1(serialize($payload).microtime(true)),
                    ...$payload,
                ];
            },
        ];
    }

    /**
     * BinaryFileResponse::getContent() es false: Inertia lo trata como
     * vacío y hace Redirect::back() (HTML). El navegador guarda `plantilla.htm`.
     */
    public function onEmptyResponse(Request $request, Response $response): Response
    {
        if ($this->isBinaryDownload($response)) {
            return $response;
        }

        return parent::onEmptyResponse($request, $response);
    }

    public function onVersionChange(Request $request, Response $response): Response
    {
        if ($this->isBinaryDownload($response)) {
            return $response;
        }

        return parent::onVersionChange($request, $response);
    }

    private function isBinaryDownload(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return true;
        }

        $disposition = (string) $response->headers->get('Content-Disposition', '');
        $type = (string) $response->headers->get('Content-Type', '');

        return str_starts_with($disposition, 'attachment')
            || str_contains($type, 'spreadsheetml')
            || str_contains($type, 'application/pdf');
    }

    /**
     * Login central: name del superadmin (users.name).
     * Tenant: lo pisa el layout con nombre_comercial.
     * Sesión iniciada: display_name del usuario (VetSaaS usa users.name, primer token en dashboard).
     */
    private function greetingName(Request $request): string
    {
        $user = $request->user();
        if ($user instanceof User) {
            $fromUser = $user->display_name !== '' ? $user->display_name : $user->first_name;

            return $fromUser !== '' ? $fromUser : (string) config('app.name');
        }

        return Cache::remember('sendsaas.platform_greeting_name', 60, function (): string {
            $email = trim((string) config('app.platform_superadmin_email'));
            $fromDb = $email !== ''
                ? User::query()->whereNull('tenant_id')->where('email', $email)->value('name')
                : null;

            $label = trim((string) ($fromDb ?: config('app.platform_superadmin_name')));

            return $label !== '' ? $label : (string) config('app.name', 'SendSaaS');
        });
    }

    /**
     * @return array{slug: string, nombre_comercial: string|null, razon_social: string|null, is_demo: bool}|null
     */
    private function sharedTenant(): ?array
    {
        if (! app()->bound(TenantManager::class)) {
            return null;
        }

        $tenant = app(TenantManager::class)->current()?->tenant;

        if ($tenant === null) {
            return null;
        }

        return [
            'slug' => (string) $tenant->slug,
            'nombre_comercial' => $tenant->nombre_comercial,
            'razon_social' => $tenant->razon_social,
            'is_demo' => DemoTenant::isSlug((string) $tenant->slug),
        ];
    }

    /**
     * @return array{tenant_id: string, tenant_label: string}|null
     */
    private function sharedImpersonation(Request $request): ?array
    {
        $imp = $request->session()->get('tenant_impersonation');

        if (is_array($imp) && ! empty($imp['tenant_id'])) {
            return [
                'tenant_id' => (string) $imp['tenant_id'],
                'tenant_label' => (string) ($imp['tenant_label'] ?? 'Empresa'),
            ];
        }

        $user = $request->user();
        $tenant = app()->bound(TenantManager::class)
            ? app(TenantManager::class)->current()?->tenant
            : null;

        if (
            $user instanceof User
            && $tenant !== null
            && $user->isPlatformSuperadmin()
        ) {
            $label = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social));

            return [
                'tenant_id' => (string) $tenant->getKey(),
                'tenant_label' => $label !== '' ? $label : 'Empresa',
            ];
        }

        return null;
    }

    /**
     * Superadmin guarda el rol en team null. En un host tenant Spatie
     * consulta el team del tenant y getRoleNames()/getAllPermissions()
     * quedan vacíos: el sidebar se veía sin menús.
     *
     * @return list<string>
     */
    private function resolveUserPermissions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->isPlatformSuperadmin()) {
            $previousTeam = getPermissionsTeamId();
            setPermissionsTeamId(null);

            try {
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                return $user->getAllPermissions()->pluck('name')->values()->all();
            } finally {
                setPermissionsTeamId($previousTeam);
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
            }
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }

    /**
     * @return list<string>
     */
    private function resolveUserRoles(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->isPlatformSuperadmin()) {
            return ['superadmin'];
        }

        return $user->getRoleNames()->values()->all();
    }
}

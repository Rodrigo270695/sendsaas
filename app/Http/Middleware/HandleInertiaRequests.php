<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

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
            'tenant_impersonation' => $this->sharedImpersonation($request),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values() ?? [],
                'roles' => $request->user()?->getRoleNames()->values() ?? [],
            ],
            'locale' => $request->getLocale(),
            'contact_whatsapp' => (string) config('app.contact_whatsapp', '51976809804'),
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
     * @return array{slug: string, nombre_comercial: string|null, razon_social: string|null}|null
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
        ];
    }

    /**
     * @return array{tenant_id: string, tenant_label: string}|null
     */
    private function sharedImpersonation(Request $request): ?array
    {
        $imp = $request->session()->get('tenant_impersonation');

        if (! is_array($imp) || empty($imp['tenant_id'])) {
            return null;
        }

        return [
            'tenant_id' => (string) $imp['tenant_id'],
            'tenant_label' => (string) ($imp['tenant_label'] ?? 'Empresa'),
        ];
    }
}

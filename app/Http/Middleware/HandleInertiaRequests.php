<?php

namespace App\Http\Middleware;

use App\Models\User;
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
            'tenant' => null,
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values() ?? [],
                'roles' => $request->user()?->getRoleNames()->values() ?? [],
            ],
            'locale' => $request->getLocale(),
            'timezone' => config('app.timezone'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
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
}

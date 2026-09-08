<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\Exceptions\TenantSuspendedException;
use App\Tenancy\Resolvers\SubdomainResolver;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        protected SubdomainResolver $resolver,
        protected TenantManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->manager->forget();

        $slug = $this->resolver->resolveFromRequest($request);

        if ($slug === null) {
            return $next($request);
        }

        try {
            $this->manager->resolveBySlug($slug);
        } catch (TenantNotFoundException) {
            abort(404, 'No encontramos esa empresa.');
        } catch (TenantSuspendedException) {
            abort(403, 'Esta empresa está suspendida o cancelada.');
        }

        return $next($request);
    }
}

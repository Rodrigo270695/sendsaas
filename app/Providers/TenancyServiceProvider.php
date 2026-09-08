<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenancy\Resolvers\SubdomainResolver;
use App\Tenancy\TenantManager;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SubdomainResolver::class);
        $this->app->singleton(TenantManager::class);
    }
}

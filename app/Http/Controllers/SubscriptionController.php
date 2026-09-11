<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Subscriptions\TenantSubscriptionSummary;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function show(): Response
    {
        $tenant = current_tenant();
        abort_if($tenant === null, 404);

        return Inertia::render('configuracion/suscripcion/index', [
            'subscription' => TenantSubscriptionSummary::forTenant($tenant),
        ]);
    }
}

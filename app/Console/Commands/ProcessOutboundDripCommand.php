<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Outbound\OutboundDripDispatcher;
use App\Tenancy\Exceptions\TenantSuspendedException;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

class ProcessOutboundDripCommand extends Command
{
    protected $signature = 'sendsaas:outbound-drip
                            {--slug= : Solo este tenant}
                            {--limit=1 : Máximo de mensajes drip por tenant en este tick}';

    protected $description = 'Despacha la cola outbound_queue con pacing de ventana y cuota del plan.';

    public function handle(OutboundDripDispatcher $dispatcher, TenantManager $tenancy): int
    {
        if (! (bool) config('outbound.drip_enabled', true)) {
            $this->warn('OUTBOUND_DRIP_ENABLED=false. No se despachó nada.');

            return self::SUCCESS;
        }

        $query = Tenant::query()
            ->whereIn('estado', ['active', 'trial', 'grace'])
            ->orderBy('slug');

        $slug = trim((string) $this->option('slug'));
        if ($slug !== '') {
            $query->where('slug', $slug);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants activos para el drip.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $sent = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenancy->resolveById((string) $tenant->id);
                $result = $dispatcher->processTenant($tenant, $limit);
            } catch (TenantSuspendedException) {
                $this->line($tenant->slug.'  skip  suspended');

                continue;
            } finally {
                $tenancy->forget();
            }

            $sent += $result['sent'];
            $failed += $result['failed'];

            $this->line(sprintf(
                '%s  sent=%d  failed=%d  skip=%s',
                $tenant->slug,
                $result['sent'],
                $result['failed'],
                $result['skipped'] ?? '-',
            ));
        }

        $this->info(sprintf('Drip: %d enviados, %d fallidos.', $sent, $failed));

        return self::SUCCESS;
    }
}

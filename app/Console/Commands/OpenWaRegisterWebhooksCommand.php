<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TenantWhatsappSession;
use App\Services\OpenWa\TenantWhatsappWebhookRegistrar;
use Illuminate\Console\Command;

class OpenWaRegisterWebhooksCommand extends Command
{
    protected $signature = 'sendsaas:openwa-register-webhooks
                            {--slug= : Solo el tenant con este slug}
                            {--dry-run : Lista sesiones ready sin llamar a OpenWA}';

    protected $description = 'Alinea el webhook inbound de OpenWA en las sesiones conectadas.';

    public function handle(TenantWhatsappWebhookRegistrar $registrar): int
    {
        $query = TenantWhatsappSession::query()
            ->with('tenant')
            ->where('status', TenantWhatsappSession::STATUS_CONNECTED)
            ->whereNotNull('openwa_session_id');

        $slug = trim((string) $this->option('slug'));
        if ($slug !== '') {
            $query->whereHas('tenant', fn ($builder) => $builder->where('slug', $slug));
        }

        $sessions = $query->get();
        if ($sessions->isEmpty()) {
            $this->warn('No hay sesiones ready para registrar.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $failed = 0;

        foreach ($sessions as $session) {
            $url = $registrar->inboxUrl((string) ($session->tenant?->slug ?? ''));
            $this->line(sprintf(
                '%s  %s  %s',
                $session->tenant?->slug ?? '?',
                $session->openwa_session_name,
                $url,
            ));

            if ($dryRun) {
                continue;
            }

            try {
                $registrar->ensureForSessionOrFail($session);
                $hooks = $registrar->inboxHooks($session);
                if ($hooks === []) {
                    $this->error('  OpenWA no devolvió un webhook de bandeja.');
                    $failed++;

                    continue;
                }

                foreach ($hooks as $hook) {
                    $this->info(sprintf(
                        '  ok  %s  active=%s',
                        $hook['url'],
                        json_encode($hook['active']),
                    ));
                }
            } catch (\Throwable $e) {
                $this->error('  fallo: '.$e->getMessage());
                $failed++;
            }
        }

        if ($dryRun) {
            $this->info('Dry-run: no se llamó a OpenWA.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

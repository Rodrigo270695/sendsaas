<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TenantWhatsappSession;
use App\Services\Conversations\OpenWaInboxSynchronizer;
use Illuminate\Console\Command;

class SyncOpenWaInboxCommand extends Command
{
    protected $signature = 'sendsaas:openwa-sync-inbox
                            {--slug= : Solo el tenant con este slug}
                            {--limit=40 : Mensajes recientes a revisar por sesión}';

    protected $description = 'Trae mensajes incoming de OpenWA a la bandeja cuando el webhook no dispara.';

    public function handle(OpenWaInboxSynchronizer $sync): int
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
            $this->warn('No hay sesiones conectadas para sincronizar.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $ingested = 0;

        foreach ($sessions as $session) {
            $stats = $sync->syncSession($session, $limit);
            $ingested += $stats['ingested'];
            $this->line(sprintf(
                '%s  %s  ingested=%d skipped=%d errors=%d',
                $session->tenant?->slug ?? '?',
                $session->openwa_session_name,
                $stats['ingested'],
                $stats['skipped'],
                $stats['errors'],
            ));
        }

        $this->info('Listo. Nuevos en bandeja: '.$ingested);

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OutboundQueueItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutboundQueueController extends Controller
{
    private const PER_PAGE = 20;

    private const STATUS_FILTERS = ['todas', 'queued', 'reserved', 'sent', 'failed', 'cancelled'];

    public function index(Request $request): Response
    {
        abort_if(current_tenant() === null, 404);

        $status = (string) $request->query('status', 'todas');
        if (! in_array($status, self::STATUS_FILTERS, true)) {
            $status = 'todas';
        }

        $query = OutboundQueueItem::query()->latest('available_at');
        if ($status !== 'todas') {
            $query->where('status', $status);
        }

        $items = $query->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('comunicaciones/envios/index', [
            'items' => $items,
            'filters' => ['status' => $status],
            'stats' => [
                'queued' => OutboundQueueItem::query()->where('status', OutboundQueueItem::STATUS_QUEUED)->count(),
                'reserved' => OutboundQueueItem::query()->where('status', OutboundQueueItem::STATUS_RESERVED)->count(),
                'sent' => OutboundQueueItem::query()->where('status', OutboundQueueItem::STATUS_SENT)->count(),
                'failed' => OutboundQueueItem::query()->where('status', OutboundQueueItem::STATUS_FAILED)->count(),
            ],
        ]);
    }
}

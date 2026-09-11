<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Support\WhatsApp\WhatsAppPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    private const PER_PAGE = 40;

    private const STATUS_FILTERS = ['todas', 'OPEN', 'PENDING', 'RESOLVED', 'CLOSED'];

    public function index(Request $request): Response
    {
        return $this->render($request, null);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->tenantIdOrAbort();

        if ($conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }

        return $this->render($request, $conversation->fresh(['contact']) ?? $conversation);
    }

    private function render(Request $request, ?Conversation $selected): Response
    {
        $this->tenantIdOrAbort();

        $search = trim((string) $request->string('search', ''));
        $status = (string) $request->string('status', 'todas');
        if (! in_array($status, self::STATUS_FILTERS, true)) {
            $status = 'todas';
        }
        $unreadOnly = $request->boolean('unread');

        $query = $this->buildListQuery($search, $status, $unreadOnly)
            ->with(['contact:id,name,phone', 'latestMessage']);

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $base = Conversation::query();

        return Inertia::render('bandeja/conversaciones/index', [
            'conversations' => $conversations->through(fn (Conversation $row): array => $this->mapListItem($row)),
            'selected' => $selected !== null ? $this->mapSelected($selected) : null,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'unread' => $unreadOnly,
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'open' => (clone $base)->where('status', Conversation::STATUS_OPEN)->count(),
                'unread' => (clone $base)->where('unread_count', '>', 0)->count(),
            ],
        ]);
    }

    /**
     * @return Builder<Conversation>
     */
    private function buildListQuery(string $search, string $status, bool $unreadOnly): Builder
    {
        $query = Conversation::query();

        if ($status !== 'todas') {
            $query->where('status', $status);
        }

        if ($unreadOnly) {
            $query->where('unread_count', '>', 0);
        }

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereHas('contact', function (Builder $contact) use ($term): void {
                    $contact->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
                })->orWhereHas('messages', function (Builder $message) use ($term): void {
                    $message->whereRaw('LOWER(COALESCE(body, \'\')) LIKE ?', [$term]);
                });
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapListItem(Conversation $conversation): array
    {
        $preview = $conversation->latestMessage;
        $body = trim((string) ($preview?->body ?? ''));

        if ($body === '' && $preview !== null) {
            $body = $preview->message_type !== 'text' ? $preview->message_type : '';
        }

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'unread_count' => $conversation->unread_count,
            'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
            'preview' => $body !== '' ? mb_substr($body, 0, 120) : null,
            'contact' => $this->mapContact($conversation),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSelected(Conversation $conversation): array
    {
        $conversation->loadMissing('contact:id,name,phone');

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'direction' => $message->direction,
                'sender_type' => $message->sender_type,
                'message_type' => $message->message_type,
                'body' => $message->body,
                'media_url' => $message->media_url,
                'sent_at' => optional($message->sent_at ?? $message->created_at)->toIso8601String(),
            ])
            ->all();

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'unread_count' => $conversation->unread_count,
            'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
            'contact' => $this->mapContact($conversation),
            'messages' => $messages,
        ];
    }

    /**
     * @return array{id: string|null, name: string, phone: string, phone_display: string}
     */
    private function mapContact(Conversation $conversation): array
    {
        $phone = (string) ($conversation->contact?->phone ?? '');

        return [
            'id' => $conversation->contact?->id,
            'name' => (string) ($conversation->contact?->name ?? $phone),
            'phone' => $phone,
            'phone_display' => WhatsAppPhone::formatDisplay($phone) ?: $phone,
        ];
    }

    private function tenantIdOrAbort(): string
    {
        $id = tenant_id();
        abort_if($id === null || $id === '', 403, 'Solo usuarios de una empresa pueden ver la bandeja.');

        return $id;
    }
}

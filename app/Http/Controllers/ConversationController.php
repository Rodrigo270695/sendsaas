<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AgentReplyException;
use App\Http\Requests\ConversationAssignRequest;
use App\Http\Requests\ConversationReplyRequest;
use App\Http\Requests\ConversationTagsRequest;
use App\Models\Conversation;
use App\Models\QuickReply;
use App\Models\Tag;
use App\Models\TenantWhatsappSession;
use App\Models\User;
use App\Services\Billing\OutboundDailyQuota;
use App\Services\Conversations\AgentReplyService;
use App\Services\OpenWa\OpenWaClient;
use App\Support\Plan\PlanLimits;
use App\Support\WhatsApp\WhatsAppPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    private const PER_PAGE = 40;

    private const STATUS_FILTERS = ['todas', 'OPEN', 'PENDING', 'RESOLVED', 'CLOSED'];

    private const ASSIGNED_FILTERS = ['todas', 'mias', 'sin_asignar'];

    public function index(Request $request): Response
    {
        return $this->render($request, null);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->tenantIdOrAbort();
        $this->assertVisible($request, $conversation);

        if ($conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }

        return $this->render($request, $conversation->fresh(['contact', 'tags', 'assignedUser']) ?? $conversation);
    }

    public function assign(ConversationAssignRequest $request, Conversation $conversation): RedirectResponse
    {
        $tenantId = $this->tenantIdOrAbort();
        $this->assertVisible($request, $conversation);

        $targetId = $request->validated('assigned_user_id');
        $targetId = is_string($targetId) && $targetId !== '' ? $targetId : null;
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->authorizeAssignment($actor, $conversation, $targetId);

        if ($targetId !== null) {
            $exists = User::query()
                ->whereKey($targetId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->exists();
            abort_unless($exists, 422, 'El usuario no pertenece a esta empresa.');
        }

        $conversation->forceFill(['assigned_user_id' => $targetId])->save();

        return back()->with('success', $targetId === null ? 'Conversación sin asignar.' : 'Conversación asignada.');
    }

    public function syncTags(ConversationTagsRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->tenantIdOrAbort();
        $this->assertVisible($request, $conversation);

        $names = collect($request->validated('names'))
            ->map(fn (mixed $name): string => mb_strtoupper(trim((string) $name)))
            ->filter()
            ->unique()
            ->values();

        $ids = $names->map(function (string $name): string {
            $tag = Tag::query()->firstOrCreate(
                ['name' => $name],
                ['color' => '#AB3C3D'],
            );

            return (string) $tag->id;
        })->all();

        $conversation->tags()->sync($ids);
        $conversation->contact?->tags()->sync($ids);

        return back()->with('success', 'Etiquetas actualizadas.');
    }

    public function reply(
        ConversationReplyRequest $request,
        Conversation $conversation,
        AgentReplyService $replies,
    ): RedirectResponse {
        $this->tenantIdOrAbort();
        $this->assertVisible($request, $conversation);

        try {
            $replies->send($conversation, (string) $request->validated('body'), $request->user());
        } catch (AgentReplyException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Mensaje enviado.');
    }

    private function render(Request $request, ?Conversation $selected): Response
    {
        $this->tenantIdOrAbort();

        $search = trim((string) $request->string('search', ''));
        $status = (string) $request->string('status', 'todas');
        if (! in_array($status, self::STATUS_FILTERS, true)) {
            $status = 'todas';
        }
        $assigned = (string) $request->string('assigned', 'todas');
        if (! in_array($assigned, self::ASSIGNED_FILTERS, true)) {
            $assigned = 'todas';
        }
        $unreadOnly = $request->boolean('unread');

        $query = $this->buildListQuery($request, $search, $status, $assigned, $unreadOnly)
            ->with(['contact:id,name,phone', 'latestMessage', 'assignedUser:id,name', 'tags:id,name,color']);

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $base = $this->visibleQuery($request);

        return Inertia::render('bandeja/conversaciones/index', [
            'conversations' => $conversations->through(fn (Conversation $row): array => $this->mapListItem($row)),
            'selected' => $selected !== null ? $this->mapSelected($selected) : null,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'assigned' => $assigned,
                'unread' => $unreadOnly,
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'open' => (clone $base)->where('status', Conversation::STATUS_OPEN)->count(),
                'unread' => (clone $base)->where('unread_count', '>', 0)->count(),
                'unassigned' => (clone $base)->whereNull('assigned_user_id')->count(),
            ],
            'reply' => $this->replyState(),
            'assignees' => $this->assignees(),
            'tag_catalog' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'quick_replies' => QuickReply::query()
                ->orderBy('title')
                ->get(['id', 'title', 'shortcut', 'body']),
            'capabilities' => [
                'assign' => $request->user()?->can('conversations.assign') ?? false,
                'manage_replies' => $request->user()?->can('conversations.assign') ?? false,
            ],
        ]);
    }

    /**
     * @return Builder<Conversation>
     */
    private function buildListQuery(
        Request $request,
        string $search,
        string $status,
        string $assigned,
        bool $unreadOnly,
    ): Builder {
        $query = $this->visibleQuery($request);

        if ($status !== 'todas') {
            $query->where('status', $status);
        }

        if ($assigned === 'mias') {
            $query->where('assigned_user_id', $request->user()?->id);
        } elseif ($assigned === 'sin_asignar') {
            $query->whereNull('assigned_user_id');
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
            'assigned_user' => $this->mapAssignee($conversation),
            'tags' => $this->mapTags($conversation),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSelected(Conversation $conversation): array
    {
        $conversation->loadMissing(['contact:id,name,phone', 'assignedUser:id,name', 'tags:id,name,color']);

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
            'assigned_user' => $this->mapAssignee($conversation),
            'tags' => $this->mapTags($conversation),
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

    /**
     * @return array{can: bool, reason: string|null, remaining: int|null}
     */
    private function replyState(): array
    {
        $tenant = current_tenant();
        if ($tenant === null) {
            return ['can' => false, 'reason' => 'tenant', 'remaining' => null];
        }

        $quota = app(OutboundDailyQuota::class);
        $remaining = null;
        $tenant->loadMissing('plan');
        $limit = PlanLimits::intLimit($tenant->plan, 'max_outbound_per_day');
        if ($limit !== null) {
            $remaining = max(0, $limit - $quota->usedToday($tenant));
        }

        if (! app(OpenWaClient::class)->isConfigured()) {
            return ['can' => false, 'reason' => 'openwa', 'remaining' => $remaining];
        }

        if (Cache::has('openwa:cooldown')) {
            return ['can' => false, 'reason' => 'cooldown', 'remaining' => $remaining];
        }

        $hasReady = TenantWhatsappSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantWhatsappSession::STATUS_CONNECTED)
            ->whereNotNull('openwa_session_id')
            ->exists();

        if (! $hasReady) {
            return ['can' => false, 'reason' => 'session', 'remaining' => $remaining];
        }

        if ($quota->wouldExceed($tenant)) {
            return ['can' => false, 'reason' => 'quota', 'remaining' => 0];
        }

        return ['can' => true, 'reason' => null, 'remaining' => $remaining];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function mapAssignee(Conversation $conversation): ?array
    {
        $user = $conversation->assignedUser;
        if ($user === null) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => $user->display_name !== '' ? $user->display_name : $user->email,
        ];
    }

    /**
     * @return list<array{id: string, name: string, color: string}>
     */
    private function mapTags(Conversation $conversation): array
    {
        return $conversation->tags
            ->map(fn (Tag $tag): array => [
                'id' => (string) $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function assignees(): array
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            return [];
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => $user->display_name !== '' ? $user->display_name : $user->email,
            ])
            ->all();
    }

    /**
     * @return Builder<Conversation>
     */
    private function visibleQuery(Request $request): Builder
    {
        $query = Conversation::query();
        $user = $request->user();

        if ($user === null || $user->can('conversations.assign')) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->whereNull('assigned_user_id')
                ->orWhere('assigned_user_id', $user->id);
        });
    }

    private function assertVisible(Request $request, Conversation $conversation): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        if ($user->can('conversations.assign')) {
            return;
        }

        if ($conversation->assigned_user_id === null || $conversation->assigned_user_id === $user->id) {
            return;
        }

        abort(403, 'Esta conversación está asignada a otro agente.');
    }

    private function authorizeAssignment(User $actor, Conversation $conversation, ?string $targetId): void
    {
        if ($actor->can('conversations.assign')) {
            return;
        }

        $mine = $conversation->assigned_user_id === $actor->id;
        $free = $conversation->assigned_user_id === null;
        $taking = $targetId === $actor->id;
        $releasing = $targetId === null && $mine;

        abort_unless(($taking && ($free || $mine)) || $releasing, 403);
    }

    private function tenantIdOrAbort(): string
    {
        $id = tenant_id();
        abort_if($id === null || $id === '', 403, 'Solo usuarios de una empresa pueden ver la bandeja.');

        return $id;
    }
}

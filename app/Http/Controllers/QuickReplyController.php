<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QuickReplyRequest;
use App\Models\QuickReply;
use Illuminate\Http\RedirectResponse;

class QuickReplyController extends Controller
{
    public function store(QuickReplyRequest $request): RedirectResponse
    {
        abort_if(tenant_id() === null, 403);

        $shortcut = $this->normalizeShortcut($request->validated('shortcut'));

        if ($shortcut !== null && QuickReply::query()->where('shortcut', $shortcut)->exists()) {
            return back()->with('error', 'Ese atajo ya existe.');
        }

        QuickReply::query()->create([
            'title' => trim((string) $request->validated('title')),
            'shortcut' => $shortcut,
            'body' => trim((string) $request->validated('body')),
            'created_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Respuesta rápida guardada.');
    }

    public function destroy(QuickReply $quickReply): RedirectResponse
    {
        abort_if(tenant_id() === null, 403);

        $quickReply->delete();

        return back()->with('success', 'Respuesta rápida eliminada.');
    }

    private function normalizeShortcut(mixed $raw): ?string
    {
        $shortcut = strtoupper(trim((string) $raw));

        return $shortcut === '' ? null : $shortcut;
    }
}

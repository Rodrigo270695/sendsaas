import { router, useForm } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { InboxCapabilities, QuickReply } from '../types';

type QuickReplyPickerProps = {
    replies: QuickReply[];
    capabilities: InboxCapabilities;
    contactName: string;
    onPick: (body: string) => void;
};

export function applyQuickReplyBody(body: string, contactName: string): string {
    return body.replace(/\{\{\s*nombre\s*\}\}/gi, contactName);
}

export function QuickReplyPicker({
    replies,
    capabilities,
    contactName,
    onPick,
}: QuickReplyPickerProps) {
    const { t } = useTranslation('bandeja');
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: '',
        shortcut: '',
        body: '',
    });

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-10 shrink-0 cursor-pointer"
                    title={t('thread.quick_replies')}
                >
                    <Zap className="size-4" />
                    <span className="sr-only">{t('thread.quick_replies')}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" side="top" className="w-80 space-y-3 p-3">
                <p className="text-[11px] font-medium text-muted-foreground">
                    {t('thread.quick_replies')}
                </p>
                {replies.length === 0 ? (
                    <p className="text-xs text-muted-foreground">{t('thread.quick_empty')}</p>
                ) : (
                    <ul className="max-h-48 space-y-1 overflow-y-auto">
                        {replies.map((reply) => (
                            <li key={reply.id} className="flex items-start gap-1">
                                <button
                                    type="button"
                                    className="min-w-0 flex-1 cursor-pointer rounded-md px-2 py-1.5 text-left hover:bg-muted"
                                    onClick={() => {
                                        onPick(applyQuickReplyBody(reply.body, contactName));
                                        setOpen(false);
                                    }}
                                >
                                    <span className="block text-xs font-semibold">
                                        {reply.shortcut ? `${reply.shortcut} · ` : ''}
                                        {reply.title}
                                    </span>
                                    <span className="line-clamp-2 text-[11px] text-muted-foreground">
                                        {reply.body}
                                    </span>
                                </button>
                                {capabilities.manage_replies ? (
                                    <button
                                        type="button"
                                        className="cursor-pointer px-1 text-[10px] text-muted-foreground hover:text-destructive"
                                        onClick={() =>
                                            router.delete(
                                                `/bandeja/respuestas-rapidas/${reply.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('thread.quick_delete')}
                                    </button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
                {capabilities.manage_replies ? (
                    <form
                        className="space-y-1.5 border-t border-border/60 pt-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/bandeja/respuestas-rapidas', {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        <input
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            placeholder={t('thread.quick_title')}
                            className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
                            required
                        />
                        <input
                            value={form.data.shortcut}
                            onChange={(event) => form.setData('shortcut', event.target.value)}
                            placeholder={t('thread.quick_shortcut')}
                            className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
                        />
                        <textarea
                            value={form.data.body}
                            onChange={(event) => form.setData('body', event.target.value)}
                            placeholder={t('thread.quick_body')}
                            rows={2}
                            className="w-full rounded-md border border-input bg-background px-2 py-1 text-xs"
                            required
                        />
                        <Button type="submit" size="sm" className="h-7 cursor-pointer text-[11px]" disabled={form.processing}>
                            {t('thread.quick_add')}
                        </Button>
                    </form>
                ) : null}
            </PopoverContent>
        </Popover>
    );
}

import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Image, Paperclip, Send } from 'lucide-react';
import { useEffect, useRef, type FormEvent, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { conversationInitials, formatInboxWhen } from '../lib';
import type { ConversationMessage, ReplyState, SelectedConversation } from '../types';

type ConversationThreadProps = {
    selected: SelectedConversation | null;
    reply: ReplyState;
};

export function ConversationThread({ selected, reply }: ConversationThreadProps) {
    const { t } = useTranslation('bandeja');
    const endRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [selected?.id, selected?.messages.length]);

    if (selected === null) {
        return (
            <div className="hidden h-full items-center justify-center md:flex">
                <EmptyState
                    title={t('empty.pick_title')}
                    description={t('empty.pick_description')}
                />
            </div>
        );
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <header className="flex items-center gap-3 border-b border-border/60 px-3 py-3 md:px-5">
                <Button variant="ghost" size="icon" className="md:hidden" asChild>
                    <Link href="/bandeja/conversaciones" preserveState>
                        <ArrowLeft className="size-4" />
                        <span className="sr-only">{t('thread.back')}</span>
                    </Link>
                </Button>
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                    {conversationInitials(selected.contact.name)}
                </span>
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">
                        {selected.contact.name}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {selected.contact.phone_display}
                    </p>
                </div>
                <span className="ml-auto rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                    {t(`status.${selected.status}`)}
                </span>
            </header>

            <div className="min-h-0 flex-1 overflow-y-auto px-3 py-4 md:px-6">
                <div className="mx-auto flex max-w-2xl flex-col gap-2">
                    {selected.messages.map((message) => (
                        <MessageBubble key={message.id} message={message} />
                    ))}
                    <div ref={endRef} />
                </div>
            </div>

            <ReplyComposer conversationId={selected.id} reply={reply} />
        </div>
    );
}

function MessageBubble({ message }: { message: ConversationMessage }) {
    const { t } = useTranslation('bandeja');
    const inbound = message.direction === 'in';
    const system = message.sender_type === 'system';
    const mediaLabel = mediaCaption(message.message_type, t);

    if (system) {
        return (
            <p className="px-6 py-1 text-center text-[11px] text-muted-foreground">
                {message.body}
            </p>
        );
    }

    return (
        <div className={cn('flex', inbound ? 'justify-start' : 'justify-end')}>
            <div
                className={cn(
                    'max-w-[80%] rounded-2xl px-3 py-2 text-sm shadow-sm',
                    inbound
                        ? 'rounded-bl-md bg-muted text-foreground'
                        : 'rounded-br-md bg-brand-600 text-white',
                )}
            >
                {message.body && (
                    <p className="whitespace-pre-wrap wrap-break-word">{message.body}</p>
                )}
                {(mediaLabel || message.media_url) && (
                    <p
                        className={cn(
                            'mt-1 flex items-center gap-1 text-xs',
                            inbound ? 'text-muted-foreground' : 'text-white/80',
                        )}
                    >
                        {message.message_type === 'image' ? (
                            <Image className="size-3.5" />
                        ) : (
                            <Paperclip className="size-3.5" />
                        )}
                        {message.media_url ? (
                            <a
                                href={message.media_url}
                                target="_blank"
                                rel="noreferrer"
                                className="underline"
                            >
                                {mediaLabel}
                            </a>
                        ) : (
                            mediaLabel
                        )}
                    </p>
                )}
                <p
                    className={cn(
                        'mt-1 text-right text-[10px]',
                        inbound ? 'text-muted-foreground' : 'text-white/70',
                    )}
                >
                    {formatInboxWhen(message.sent_at)}
                </p>
            </div>
        </div>
    );
}

function ReplyComposer({
    conversationId,
    reply,
}: {
    conversationId: string;
    reply: ReplyState;
}) {
    const { t } = useTranslation('bandeja');
    const { can } = usePermission();
    const canPermission = can('conversations.reply');
    const enabled = canPermission && reply.can;

    if (!canPermission) {
        return null;
    }
    const form = useForm({ body: '' });

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        if (!enabled || form.processing || form.data.body.trim() === '') {
            return;
        }

        form.post(`/bandeja/conversaciones/${conversationId}/mensajes`, {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-2 border-t border-border/60 px-3 py-3 md:px-5"
        >
            {!enabled && (
                <p className="text-xs text-muted-foreground">
                    {blockedReason
                        ? t(`thread.reply_blocked.${blockedReason}`)
                        : t('thread.reply_blocked.tenant')}
                </p>
            )}
            {enabled && reply.remaining !== null && (
                <p className="text-xs text-muted-foreground">
                    {t('thread.reply_remaining', { count: reply.remaining })}
                </p>
            )}
            <div className="flex items-end gap-2">
                <Textarea
                    value={form.data.body}
                    onChange={(event) => form.setData('body', event.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder={t('thread.reply_placeholder')}
                    disabled={!enabled || form.processing}
                    autoGrow
                    rows={1}
                    className="min-h-11 max-h-36"
                    aria-label={t('thread.reply_placeholder')}
                />
                <Button
                    type="submit"
                    disabled={!enabled || form.processing || form.data.body.trim() === ''}
                    className="cursor-pointer"
                >
                    <Send className="size-4" />
                    {form.processing ? t('thread.reply_sending') : t('thread.reply_send')}
                </Button>
            </div>
        </form>
    );
}

function mediaCaption(
    type: string,
    t: (key: string) => string,
): string | null {
    return (
        {
            image: t('thread.media_image'),
            document: t('thread.media_document'),
            audio: t('thread.media_audio'),
            video: t('thread.media_video'),
        }[type] ?? (type !== 'text' ? t('thread.media_other') : null)
    );
}

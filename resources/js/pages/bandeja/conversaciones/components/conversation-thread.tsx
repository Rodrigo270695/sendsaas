import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Image, Paperclip, SendHorizontal } from 'lucide-react';
import { useEffect, useMemo, useRef, type FormEvent, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import {
    buildInboxThreadItems,
    conversationInitials,
    formatThreadClock,
} from '../lib';
import type {
    InboxCapabilities,
    InboxTag,
    InboxUser,
    ConversationMessage,
    QuickReply,
    ReplyState,
    SelectedConversation,
} from '../types';
import { ConversationAssignment } from './conversation-assignment';
import { ConversationTags } from './conversation-tags';
import { QuickReplyPicker } from './quick-reply-picker';

type ConversationThreadProps = {
    selected: SelectedConversation | null;
    reply: ReplyState;
    assignees: InboxUser[];
    tagCatalog: InboxTag[];
    quickReplies: QuickReply[];
    capabilities: InboxCapabilities;
};

export function ConversationThread({
    selected,
    reply,
    assignees,
    tagCatalog,
    quickReplies,
    capabilities,
}: ConversationThreadProps) {
    const { t } = useTranslation('bandeja');
    const endRef = useRef<HTMLDivElement | null>(null);
    const items = useMemo(
        () =>
            selected
                ? buildInboxThreadItems(selected.messages, {
                      today: t('dates.today'),
                      yesterday: t('dates.yesterday'),
                  })
                : [],
        [selected, t],
    );

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [selected?.id, selected?.messages.length]);

    if (selected === null) {
        return (
            <div className="hidden h-full flex-1 items-center justify-center md:flex">
                <EmptyState
                    title={t('empty.pick_title')}
                    description={t('empty.pick_description')}
                />
            </div>
        );
    }

    return (
        <div className="flex h-full min-h-0 w-full flex-col">
            <header className="flex items-center gap-3 border-b border-border/60 bg-card/90 px-2 py-2.5 backdrop-blur-md sm:px-4 sm:py-3">
                <Button variant="ghost" size="icon" className="md:hidden" asChild>
                    <Link href="/bandeja/conversaciones" preserveState>
                        <ArrowLeft className="size-4" />
                        <span className="sr-only">{t('thread.back')}</span>
                    </Link>
                </Button>
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border/50 bg-brand-100 text-xs font-semibold text-brand-800 shadow-sm sm:size-10 dark:bg-brand-950/60 dark:text-brand-100">
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
                <div className="ml-auto flex min-w-0 flex-wrap items-center justify-end gap-1.5">
                    <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                        {t(`status.${selected.status}`)}
                    </span>
                    <ConversationAssignment
                        selected={selected}
                        assignees={assignees}
                        capabilities={capabilities}
                    />
                    <ConversationTags selected={selected} catalog={tagCatalog} />
                </div>
            </header>

            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-4 sm:px-4">
                <div className="mx-auto flex min-h-full max-w-2xl flex-col justify-end gap-2.5">
                    {items.map((item) =>
                        item.kind === 'sep' ? (
                            <div
                                key={item.key}
                                className="flex items-center justify-center py-1"
                            >
                                <span className="rounded-full bg-muted/80 px-3 py-0.5 text-[10px] font-medium text-muted-foreground">
                                    {item.label}
                                </span>
                            </div>
                        ) : (
                            <MessageBubble key={item.key} message={item.message} />
                        ),
                    )}
                    <div ref={endRef} />
                </div>
            </div>

            <ReplyComposer
                conversationId={selected.id}
                contactName={selected.contact.name}
                reply={reply}
                quickReplies={quickReplies}
                capabilities={capabilities}
            />
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
                    'relative max-w-[min(80%,30rem)] overflow-hidden rounded-2xl px-3 py-2 text-sm shadow-sm',
                    inbound
                        ? 'rounded-bl-md border border-border/60 bg-card text-foreground'
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
                        'mt-1 flex items-center justify-end gap-1 text-[10px]',
                        inbound ? 'text-muted-foreground' : 'text-brand-100/90',
                    )}
                >
                    {formatThreadClock(message.sent_at)}
                    {!inbound && <Check className="size-3 shrink-0 opacity-80" />}
                </p>
            </div>
        </div>
    );
}

function ReplyComposer({
    conversationId,
    contactName,
    reply,
    quickReplies,
    capabilities,
}: {
    conversationId: string;
    contactName: string;
    reply: ReplyState;
    quickReplies: QuickReply[];
    capabilities: InboxCapabilities;
}) {
    const { t } = useTranslation('bandeja');
    const { can } = usePermission();
    const form = useForm({ body: '' });
    const canPermission = can('conversations.reply');
    const enabled = canPermission && reply.can;

    if (!canPermission) {
        return null;
    }

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
            className="space-y-2 border-t border-border/60 bg-card/95 px-3 py-3 backdrop-blur-md md:px-4"
        >
            {!enabled && (
                <p className="text-xs text-muted-foreground">
                    {reply.reason
                        ? t(`thread.reply_blocked.${reply.reason}`)
                        : t('thread.reply_blocked.tenant')}
                </p>
            )}
            {enabled && reply.remaining !== null && (
                <p className="text-xs text-muted-foreground">
                    {t('thread.reply_remaining', { count: reply.remaining })}
                </p>
            )}
            <div className="flex items-end gap-1.5">
                <QuickReplyPicker
                    replies={quickReplies}
                    capabilities={capabilities}
                    contactName={contactName}
                    onPick={(body) => form.setData('body', body)}
                />
                <Textarea
                    value={form.data.body}
                    onChange={(event) => form.setData('body', event.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder={t('thread.reply_placeholder')}
                    disabled={!enabled || form.processing}
                    autoGrow
                    rows={1}
                    className="min-h-10 max-h-28 flex-1 resize-none bg-background/70"
                    aria-label={t('thread.reply_placeholder')}
                />
                <Button
                    type="submit"
                    size="icon"
                    disabled={!enabled || form.processing || form.data.body.trim() === ''}
                    className="size-10 shrink-0 cursor-pointer bg-brand-600 hover:bg-brand-700"
                >
                    <SendHorizontal className="size-4" />
                    <span className="sr-only">
                        {form.processing ? t('thread.reply_sending') : t('thread.reply_send')}
                    </span>
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

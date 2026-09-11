import type { Paginated } from '@/types';

export type ConversationStatus = 'OPEN' | 'PENDING' | 'RESOLVED' | 'CLOSED';

export type ConversationContact = {
    id: string | null;
    name: string;
    phone: string;
    phone_display: string;
};

export type ConversationListItem = {
    id: string;
    status: ConversationStatus;
    unread_count: number;
    last_message_at: string | null;
    preview: string | null;
    contact: ConversationContact;
};

export type ConversationMessage = {
    id: string;
    direction: 'in' | 'out';
    sender_type: 'contact' | 'agent' | 'system';
    message_type: string;
    body: string | null;
    media_url: string | null;
    sent_at: string | null;
};

export type SelectedConversation = ConversationListItem & {
    messages: ConversationMessage[];
};

export type ConversationFilters = {
    search: string;
    status: 'todas' | ConversationStatus;
    unread: boolean;
};

export type ConversationStats = {
    total: number;
    open: number;
    unread: number;
};

export type ReplyState = {
    can: boolean;
    reason: 'tenant' | 'openwa' | 'cooldown' | 'session' | 'quota' | null;
    remaining: number | null;
};

export type InboxPageProps = {
    conversations: Paginated<ConversationListItem>;
    selected: SelectedConversation | null;
    filters: ConversationFilters;
    stats: ConversationStats;
    reply: ReplyState;
};

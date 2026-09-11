import type { Paginated } from '@/types';

export type ConversationStatus = 'OPEN' | 'PENDING' | 'RESOLVED' | 'CLOSED';

export type ConversationContact = {
    id: string | null;
    name: string;
    phone: string;
    phone_display: string;
};

export type InboxUser = {
    id: string;
    name: string;
};

export type InboxTag = {
    id: string;
    name: string;
    color: string;
};

export type QuickReply = {
    id: string;
    title: string;
    shortcut: string | null;
    body: string;
};

export type ConversationListItem = {
    id: string;
    status: ConversationStatus;
    unread_count: number;
    last_message_at: string | null;
    preview: string | null;
    contact: ConversationContact;
    assigned_user: InboxUser | null;
    tags: InboxTag[];
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
    assigned: 'todas' | 'mias' | 'sin_asignar';
    unread: boolean;
};

export type ConversationStats = {
    total: number;
    open: number;
    unread: number;
    unassigned: number;
};

export type ReplyState = {
    can: boolean;
    reason: 'tenant' | 'openwa' | 'cooldown' | 'session' | 'quota' | null;
    remaining: number | null;
};

export type InboxCapabilities = {
    assign: boolean;
    manage_replies: boolean;
};

export type InboxPageProps = {
    conversations: Paginated<ConversationListItem>;
    selected: SelectedConversation | null;
    filters: ConversationFilters;
    stats: ConversationStats;
    reply: ReplyState;
    assignees: InboxUser[];
    tag_catalog: InboxTag[];
    quick_replies: QuickReply[];
    capabilities: InboxCapabilities;
};

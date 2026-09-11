export function conversationInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
}

export function formatInboxWhen(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();

    if (sameDay) {
        return date.toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return date.toLocaleDateString('es-PE', {
        day: 'numeric',
        month: 'short',
    });
}

export function formatThreadClock(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export type InboxThreadSep = { kind: 'sep'; key: string; label: string };
export type InboxThreadMsg<T> = { kind: 'msg'; key: string; message: T };
export type InboxThreadItem<T> = InboxThreadSep | InboxThreadMsg<T>;

export function buildInboxThreadItems<T extends { id: string; sent_at: string | null }>(
    messages: T[],
    labels: { today: string; yesterday: string },
): InboxThreadItem<T>[] {
    const items: InboxThreadItem<T>[] = [];
    let prevDay = '';

    for (const message of messages) {
        const date = message.sent_at ? new Date(message.sent_at) : null;
        const valid = date !== null && !Number.isNaN(date.getTime());
        const key = valid ? dayKey(date) : '';

        if (key !== '' && key !== prevDay) {
            prevDay = key;
            items.push({
                kind: 'sep',
                key: `sep-${key}`,
                label: dayLabel(date as Date, labels),
            });
        }

        items.push({ kind: 'msg', key: message.id, message });
    }

    return items;
}

function dayKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function dayLabel(date: Date, labels: { today: string; yesterday: string }): string {
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return labels.today;
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return labels.yesterday;
    }

    return date.toLocaleDateString('es-PE', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

import { router } from '@inertiajs/react';
import { Tag } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { InboxTag, SelectedConversation } from '../types';

type ConversationTagsProps = {
    selected: SelectedConversation;
    catalog: InboxTag[];
};

export function ConversationTags({ selected, catalog }: ConversationTagsProps) {
    const { t } = useTranslation('bandeja');
    const [name, setName] = useState('');
    const selectedNames = selected.tags.map((tag) => tag.name);

    const sync = (names: string[]) => {
        router.put(
            `/bandeja/conversaciones/${selected.id}/etiquetas`,
            { names },
            { preserveScroll: true, preserveState: true },
        );
    };

    const toggle = (tagName: string) => {
        const next = selectedNames.includes(tagName)
            ? selectedNames.filter((item) => item !== tagName)
            : [...selectedNames, tagName];
        sync(next);
    };

    const add = (event?: FormEvent) => {
        event?.preventDefault();
        const next = name.trim().toUpperCase();
        if (next === '') {
            return;
        }
        sync([...new Set([...selectedNames, next])]);
        setName('');
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 cursor-pointer gap-1 px-2 text-[11px]"
                >
                    <Tag className="size-3.5" />
                    {selected.tags.length > 0
                        ? selected.tags.map((tag) => tag.name).join(', ')
                        : t('thread.no_tags')}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-64 space-y-2 p-3">
                <p className="text-[11px] font-medium text-muted-foreground">
                    {t('thread.tags')}
                </p>
                <div className="flex flex-wrap gap-1.5">
                    {catalog.map((tag) => {
                        const active = selectedNames.includes(tag.name);
                        return (
                            <button
                                key={tag.id}
                                type="button"
                                onClick={() => toggle(tag.name)}
                                className={cn(
                                    'cursor-pointer rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                    active
                                        ? 'text-white'
                                        : 'bg-muted text-muted-foreground hover:text-foreground',
                                )}
                                style={
                                    active
                                        ? { backgroundColor: tag.color || '#AB3C3D' }
                                        : undefined
                                }
                            >
                                {tag.name}
                            </button>
                        );
                    })}
                </div>
                <form onSubmit={add} className="flex gap-1.5">
                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder={t('thread.add_tag_placeholder')}
                        className="h-8 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-xs"
                    />
                    <Button type="submit" size="sm" className="h-8 cursor-pointer px-2 text-[11px]">
                        {t('thread.add_tag')}
                    </Button>
                </form>
            </PopoverContent>
        </Popover>
    );
}

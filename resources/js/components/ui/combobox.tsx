import { Check, ChevronsUpDown, Loader2, X } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type ComboboxOption = {
    value: string;
    label: string;
    /** Texto extra para filtrar (p. ej. DNI) sin mostrarlo distinto del label. */
    keywords?: string;
};

function normalizeForSearch(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, '');
}

/** Cada palabra del buscador debe aparecer en el texto, en cualquier orden. */
function tokenFilter(value: string, search: string): number {
    const tokens = normalizeForSearch(search)
        .trim()
        .split(/\s+/)
        .filter((token) => token.length > 0);

    if (tokens.length === 0) {
        return 1;
    }

    const hay = normalizeForSearch(value);

    return tokens.every((token) => hay.includes(token)) ? 1 : 0;
}

export type ComboboxProps = {
    options: readonly ComboboxOption[];
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
    loading?: boolean;
    clearable?: boolean;
    id?: string;
    name?: string;
    className?: string;
    /** Se dispara al escribir en el buscador (para búsqueda remota). */
    onSearchChange?: (query: string) => void;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

/**
 * Combobox con búsqueda (cmdk). Sin alta de ítems: solo elige del catálogo.
 *
 * `modal` en el Popover evita que un Dialog padre (FormModal) capture
 * el scroll de la lista.
 */
export function Combobox({
    options,
    value,
    onChange,
    placeholder = 'Selecciona...',
    searchPlaceholder = 'Buscar...',
    emptyMessage = 'Sin resultados.',
    disabled = false,
    loading = false,
    clearable = true,
    id,
    name,
    className,
    onSearchChange,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}: ComboboxProps) {
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const listRef = React.useRef<HTMLDivElement>(null);

    const selected = React.useMemo(
        () => options.find((opt) => opt.value === value) ?? null,
        [options, value],
    );

    React.useEffect(() => {
        if (!open) {
            setSearch('');
        }
    }, [open]);

    React.useEffect(() => {
        if (!open) {
            return;
        }

        const list = listRef.current;
        if (!list) {
            return;
        }

        list.scrollTop = 0;

        const frame = requestAnimationFrame(() => {
            list.scrollTop = 0;
        });

        return () => cancelAnimationFrame(frame);
    }, [search, open]);

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange(null);
    };

    return (
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedBy}
                    id={id}
                    disabled={disabled || loading}
                    className={cn(
                        'group w-full cursor-pointer justify-between font-normal',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">
                        {selected
                            ? selected.label
                            : value?.trim()
                              ? value
                              : placeholder}
                    </span>
                    <div className="flex items-center gap-1">
                        {clearable &&
                        (selected || value) &&
                        !disabled &&
                        !loading ? (
                            <span
                                role="button"
                                aria-label="Limpiar selección"
                                tabIndex={-1}
                                onClick={handleClear}
                                onPointerDown={(e) => e.stopPropagation()}
                                className="rounded p-0.5 text-muted-foreground opacity-0 transition-opacity hover:bg-muted group-hover:opacity-100"
                            >
                                <X className="size-3.5" strokeWidth={2.5} />
                            </span>
                        ) : null}
                        {loading ? (
                            <Loader2 className="size-4 shrink-0 animate-spin opacity-50" />
                        ) : (
                            <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                        )}
                    </div>
                    {name ? (
                        <input type="hidden" name={name} value={value ?? ''} />
                    ) : null}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-(--radix-popover-trigger-width) min-w-48 p-0"
                align="start"
                sideOffset={4}
                onWheel={(e) => e.stopPropagation()}
                onTouchMove={(e) => e.stopPropagation()}
            >
                <Command filter={tokenFilter}>
                    <CommandInput
                        placeholder={searchPlaceholder}
                        value={search}
                        onValueChange={(next) => {
                            setSearch(next);
                            onSearchChange?.(next);
                        }}
                    />
                    <CommandList ref={listRef}>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        <CommandGroup>
                            {options.map((opt) => (
                                <CommandItem
                                    key={opt.value}
                                    value={`${opt.label} ${opt.keywords ?? ''} ${opt.value}`}
                                    keywords={
                                        opt.keywords
                                            ? [opt.keywords]
                                            : undefined
                                    }
                                    onSelect={() => {
                                        onChange(
                                            opt.value === value
                                                ? null
                                                : opt.value,
                                        );
                                        setOpen(false);
                                    }}
                                    className="cursor-pointer"
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            opt.value === value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {opt.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

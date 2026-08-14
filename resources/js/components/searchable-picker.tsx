import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * Options are keyed by a row id where they are records, and by a string
 * where they are not — a timezone identifier, say. The parameter is here
 * rather than a `number | string` union because `onChange` would otherwise
 * widen for every caller, and a picker over files would start having to
 * handle an id it can never be given.
 */
export interface PickerOption<Id extends number | string = number> {
    id: Id;
    label: string;
    /** Secondary line — a filename, a date. Not searched when filtering locally. */
    hint?: string;
}

interface SearchablePickerProps<Id extends number | string> {
    options: PickerOption<Id>[];
    value: Id | null;
    onChange: (id: Id | null) => void;
    searchPlaceholder?: string;
    emptyLabel?: (search: string) => string;
    /**
     * Supply this to take over filtering — the parent is searching
     * server-side and `options` is already the answer. Omit it and the
     * component filters `options` locally.
     *
     * The two callers genuinely differ: an account list is small enough to
     * ship whole, a file library is not.
     */
    onSearchChange?: (query: string) => void;
    loading?: boolean;
    className?: string;
    maxHeightClass?: string;
    autoFocus?: boolean;
}

/**
 * A search box over a scrollable list of options, single-select.
 *
 * There is no combobox primitive in this app — no `command.tsx`, no
 * `popover.tsx`, and `cmdk` is not a dependency — and every other picker is
 * a plain non-searchable `<Select>`, which is unusable against a file
 * library. This is the shape `account-content-delete-dialog.tsx` arrived at
 * for the same problem, lifted out so the second caller does not become a
 * second copy of it.
 */
export function SearchablePicker<Id extends number | string = number>({
    options,
    value,
    onChange,
    searchPlaceholder,
    emptyLabel,
    onSearchChange,
    loading = false,
    className,
    maxHeightClass = 'max-h-48',
    autoFocus = false,
}: SearchablePickerProps<Id>) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const listRef = useRef<HTMLDivElement>(null);
    const selectedRef = useRef<HTMLButtonElement>(null);

    // Bring the current choice into view on open. Without this a long
    // list starts at whatever sorts first — 400-odd timezones open on
    // "Africa / Abidjan" — and a settings screen that cannot show you its
    // own current value is worse than useless, because the first entry
    // reads as if it were the answer.
    //
    // Scrolls the list's own box and never the page: `scrollIntoView`
    // walks up to the document and would yank a form the picker sits
    // halfway down. Mount only, so clicking a row does not re-centre the
    // list under the pointer.
    useEffect(() => {
        const list = listRef.current;
        const item = selectedRef.current;

        if (list === null || item === null) {
            return;
        }

        list.scrollTop = item.offsetTop - list.clientHeight / 2 + item.clientHeight / 2;
    }, []);

    const filtered = useMemo(() => {
        if (onSearchChange) return options;

        const query = search.trim().toLowerCase();
        if (query === '') return options;

        return options.filter((option) => option.label.toLowerCase().includes(query));
    }, [options, search, onSearchChange]);

    return (
        <div className={cn('grid gap-2', className)}>
            <Input
                type="search"
                placeholder={searchPlaceholder ?? t('Search…')}
                value={search}
                onChange={(e) => {
                    setSearch(e.target.value);
                    onSearchChange?.(e.target.value);
                }}
                autoFocus={autoFocus}
            />
            {/* `relative` so a row's offsetTop is measured against this box,
                which is what the scroll-into-view above reads. */}
            <div ref={listRef} className={cn('relative overflow-y-auto rounded-md border', maxHeightClass)}>
                {loading && <p className="text-muted-foreground p-3 text-sm">{t('Searching…')}</p>}

                {!loading && filtered.length === 0 && (
                    <p className="text-muted-foreground p-3 text-sm">
                        {emptyLabel ? emptyLabel(search) : t('Nothing matches ":search".', { search })}
                    </p>
                )}

                {!loading &&
                    filtered.map((option) => {
                        const selected = value === option.id;

                        return (
                            <button
                                key={option.id}
                                ref={selected ? selectedRef : undefined}
                                type="button"
                                onClick={() => onChange(selected ? null : option.id)}
                                className={cn(
                                    'hover:bg-accent hover:text-accent-foreground flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm',
                                    selected && 'bg-accent text-accent-foreground',
                                )}
                            >
                                <span className="min-w-0">
                                    <span className="block truncate">{option.label}</span>
                                    {option.hint && <span className="text-muted-foreground block truncate text-xs">{option.hint}</span>}
                                </span>
                                {selected && <Check className="size-4 shrink-0" />}
                            </button>
                        );
                    })}
            </div>
        </div>
    );
}

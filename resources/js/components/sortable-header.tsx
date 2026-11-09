import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

export type SortDirection = 'asc' | 'desc';

interface SortProps {
    column: string;
    sort: string;
    direction: SortDirection;
    onSort: (column: string, direction: SortDirection) => void;
    /** The direction a first click picks. Sizes and dates start biggest and newest first. */
    firstDirection?: SortDirection;
    children: ReactNode;
}

const ariaSort = (active: boolean, direction: SortDirection) => (active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');

/**
 * A button that sorts by one column. The first click sorts it in its
 * natural direction; clicking the column already sorted reverses it, like
 * a desktop file manager.
 *
 * Use SortableHeader inside a table. This bare button is for lists that
 * are not tables, such as a theme's header row above a list of cards.
 */
export function SortButton({ column, sort, direction, onSort, firstDirection = 'asc', children, className }: SortProps & { className?: string }) {
    const active = sort === column;
    const Icon = !active ? ArrowUpDown : direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <button
            type="button"
            onClick={() => onSort(column, active ? (direction === 'asc' ? 'desc' : 'asc') : firstDirection)}
            className={cn(
                'hover:text-foreground focus-visible:ring-ring inline-flex items-center gap-1 rounded-sm focus-visible:ring-2 focus-visible:outline-hidden',
                className,
            )}
        >
            {children}
            <Icon className={cn('size-3.5 shrink-0', active ? 'opacity-100' : 'opacity-40')} aria-hidden />
        </button>
    );
}

/** A table header cell that sorts its column. See SortButton. */
export function SortableHeader({ className, colSpan, ...props }: SortProps & { className?: string; colSpan?: number }) {
    return (
        <th className={cn('px-4 py-2.5 font-medium', className)} colSpan={colSpan} aria-sort={ariaSort(props.sort === props.column, props.direction)}>
            <SortButton {...props} />
        </th>
    );
}

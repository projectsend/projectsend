import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

export type SortDirection = 'asc' | 'desc';

/**
 * A table header cell that sorts its column. The first click sorts it in
 * its natural direction (A to Z, smallest first, newest first); clicking
 * the column already sorted reverses it, like a desktop file manager.
 */
export function SortableHeader({
    column,
    sort,
    direction,
    onSort,
    firstDirection = 'asc',
    className,
    colSpan,
    children,
}: {
    column: string;
    sort: string;
    direction: SortDirection;
    onSort: (column: string, direction: SortDirection) => void;
    /** The direction a first click picks. Dates start newest first. */
    firstDirection?: SortDirection;
    className?: string;
    colSpan?: number;
    children: ReactNode;
}) {
    const active = sort === column;
    const Icon = !active ? ArrowUpDown : direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <th
            className={cn('px-4 py-2.5 font-medium', className)}
            colSpan={colSpan}
            aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}
        >
            <button
                type="button"
                onClick={() => onSort(column, active ? (direction === 'asc' ? 'desc' : 'asc') : firstDirection)}
                className="hover:text-foreground focus-visible:ring-ring inline-flex items-center gap-1 rounded-sm focus-visible:ring-2 focus-visible:outline-hidden"
            >
                {children}
                <Icon className={cn('size-3.5', active ? 'opacity-100' : 'opacity-40')} aria-hidden />
            </button>
        </th>
    );
}

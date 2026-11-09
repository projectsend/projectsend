import { router } from '@inertiajs/react';

import { type SortDirection } from '@/components/sortable-header';

export interface PublicListOrder {
    sort: 'name' | 'size' | 'date';
    direction: SortDirection;
}

/**
 * What every public listing page (index, group, folder, in every theme)
 * does that is not markup: changing the order of the list.
 *
 * The page size is the shared <Pagination>'s job; this keeps it, along
 * with anything else in the address, and only goes back to page 1.
 */
export function usePublicListing({ sort, direction }: PublicListOrder) {
    const sortBy = (column: string, nextDirection: SortDirection) => {
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        // Name A to Z is the default, so it is left out of the address.
        if (column === 'name' && nextDirection === 'asc') {
            params.delete('sort');
            params.delete('direction');
        } else {
            params.set('sort', column);
            params.set('direction', nextDirection);
        }
        const query = params.toString();
        router.visit(`${window.location.pathname}${query ? `?${query}` : ''}`, { preserveScroll: true });
    };

    return { sortState: { sort, direction }, sortBy };
}

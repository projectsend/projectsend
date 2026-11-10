import { router } from '@inertiajs/react';

import { type SortDirection } from '@/components/sortable-header';
import { useSelection } from '@/hooks/use-selection';
import { useZipDownload } from '@/hooks/use-zip-download';

export interface PublicListOrder {
    sort: 'name' | 'size' | 'date';
    direction: SortDirection;
}

/**
 * Present when this page lets visitors download ticked files as one zip
 * (Setting::PublicZipDownloadsEnabled, and the zip capability). Null hides
 * every checkbox.
 */
export interface PublicZip {
    store_url: string;
    scope: 'index' | 'group' | 'folder';
    slug: string | null;
}

/**
 * What every public listing page (index, group, folder, in every theme)
 * does that is not markup: changing the order of the list, and ticking
 * files to download together.
 *
 * The page size is the shared <Pagination>'s job; sorting keeps it, along
 * with anything else in the address, and only goes back to page 1.
 *
 * Files are ticked by slug, not id: public pages hand out no ids. Ticks
 * survive paging and re-sorting, and reset on a different page (another
 * folder or group), the same rule as everywhere else.
 */
export function usePublicListing({
    sort,
    direction,
    files = [],
    zip = null,
}: PublicListOrder & { files?: { slug: string }[]; zip?: PublicZip | null }) {
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

    const selection = useSelection<string>({
        scope: 'public-listing',
        resetKey: window.location.pathname,
        rows: files.map((file) => ({ kind: 'file' as const, id: file.slug })),
    });
    const zipDownload = useZipDownload(zip ? { storeUrl: zip.store_url } : undefined);

    const downloadSelectionAsZip = () => {
        if (zip === null) return;
        zipDownload.start({ scope: zip.scope, slug: zip.slug, files: [...selection.fileIds] });
    };

    return {
        sortState: { sort, direction },
        sortBy,
        /** Whether to show checkboxes and the selection bar at all. */
        canZip: zip !== null,
        selection,
        zip: zipDownload,
        downloadSelectionAsZip,
    };
}

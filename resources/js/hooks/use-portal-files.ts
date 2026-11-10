import { type SortDirection } from '@/components/sortable-header';
import { useCapability } from '@/hooks/use-capability';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useSelection } from '@/hooks/use-selection';
import { useZipDownload } from '@/hooks/use-zip-download';
import { type MyFilesProps } from '@/types/portal';

/**
 * Everything the "My files" page does that is not markup: which rows are
 * selected, zipping them, and driving the search/filter/sort query string.
 *
 * Each theme renders this data differently and that is the whole point of
 * themes — but they were also each carrying their own copy of the behaviour,
 * so a fix to selection or filtering had to be made four times to be true.
 */
export function usePortalFiles({ folder, folders, files, search, category, owner, sort, direction, pagination }: MyFilesProps) {
    const zip = useZipDownload();
    // Withheld on some hosted plans. In the portal, selecting rows exists
    // only to zip them, so themes hide the checkboxes and the selection
    // bar along with the folder's zip button when this is false.
    const canZip = useCapability('downloads.zip');

    // Kept across pages, page sizes and sorts, so a client can pick files
    // from several pages and zip them together. A new folder, search or
    // filter starts afresh: those change which rows the list holds.
    const selection = useSelection({
        scope: 'my-files.index',
        resetKey: JSON.stringify([folder?.id ?? null, search, category, owner]),
        rows: [...folders.map((row) => ({ kind: 'folder' as const, id: row.id })), ...files.map((row) => ({ kind: 'file' as const, id: row.id }))],
    });
    const downloadSelectionAsZip = () => zip.start({ file_ids: [...selection.fileIds], folder_ids: [...selection.folderIds] });

    // The page size rides along with every change, and Clear leaves it
    // alone: it is how the list is shown, not what is in it.
    const perPage = pagination.per_page !== undefined && pagination.per_page !== pagination.per_page_options?.[0] ? String(pagination.per_page) : '';

    // The open folder goes along with every change, or re-sorting inside a
    // folder navigated back to the top of My files. Search and the filters
    // still show a flat list across every folder (MyFilesController), and
    // clearing them comes back to the folder.
    const { values, set, setMany, reset } = useListQuery(
        'my-files.index',
        { search, category: category === null ? ALL : String(category), owner: owner ?? ALL, sort, direction, per_page: perPage },
        { search: '', category: ALL, owner: ALL, sort: 'date', direction: 'desc', per_page: perPage },
        folder !== null ? { folder: folder.id } : undefined,
    );
    // Sort/direction always have a concrete value (there's no "unset" sort),
    // so they're excluded here — only search/category/owner count as active
    // filters worth surfacing a "Clear" button for.
    const hasFilters = values.search !== '' || values.category !== ALL || values.owner !== ALL;

    /** For a clickable column header. */
    const sortBy = (column: string, nextDirection: SortDirection) => setMany({ sort: column, direction: nextDirection });

    // Opening a folder keeps the chosen sort and page size. Only those:
    // opening a folder means leaving a search, so the filters are not
    // carried. Defaults are left out to keep the address clean.
    const viewParams = {
        ...(values.sort === 'date' && values.direction === 'desc' ? {} : { sort: values.sort, direction: values.direction }),
        ...(values.per_page !== '' ? { per_page: values.per_page } : {}),
    };
    const folderUrl = (id: number | null) => route('my-files.index', id === null ? viewParams : { folder: id, ...viewParams });

    return {
        zip,
        canZip,
        selection,
        selectionCount: selection.count,
        clearSelection: selection.clear,
        downloadSelectionAsZip,
        values,
        set,
        setMany,
        reset,
        hasFilters,
        /** The current order, for column headers. */
        sortState: { sort: values.sort, direction: values.direction as SortDirection },
        sortBy,
        folderUrl,
    };
}

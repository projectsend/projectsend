import { useEffect, useState } from 'react';

import { ALL, useListQuery } from '@/hooks/use-list-query';
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
export function usePortalFiles({ folder, search, category, owner, sort, direction, pagination }: MyFilesProps) {
    const zip = useZipDownload();

    const [selectedFileIds, setSelectedFileIds] = useState<Set<number>>(new Set());
    const [selectedFolderIds, setSelectedFolderIds] = useState<Set<number>>(new Set());
    const selectionCount = selectedFileIds.size + selectedFolderIds.size;

    // A new folder/search/filter/sort context (or a different page) means a
    // different set of rows on screen — stale selections would silently zip
    // items no longer visible.
    useEffect(() => {
        setSelectedFileIds(new Set());
        setSelectedFolderIds(new Set());
    }, [folder?.id, search, category, owner, sort, direction, pagination.page]);

    const toggleFile = (id: number) =>
        setSelectedFileIds((current) => {
            const next = new Set(current);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    const toggleFolder = (id: number) =>
        setSelectedFolderIds((current) => {
            const next = new Set(current);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    const clearSelection = () => {
        setSelectedFileIds(new Set());
        setSelectedFolderIds(new Set());
    };
    const downloadSelectionAsZip = () => zip.start({ file_ids: [...selectedFileIds], folder_ids: [...selectedFolderIds] });

    const { values, set, setMany, reset } = useListQuery(
        'my-files.index',
        { search, category: category === null ? ALL : String(category), owner: owner ?? ALL, sort, direction },
        { search: '', category: ALL, owner: ALL, sort: 'date', direction: 'desc' },
    );
    // Sort/direction always have a concrete value (there's no "unset" sort),
    // so they're excluded here — only search/category/owner count as active
    // filters worth surfacing a "Clear" button for.
    const hasFilters = values.search !== '' || values.category !== ALL || values.owner !== ALL;

    const folderUrl = (id: number | null) => (id === null ? route('my-files.index') : route('my-files.index', { folder: id }));

    return {
        zip,
        selectedFileIds,
        selectedFolderIds,
        selectionCount,
        toggleFile,
        toggleFolder,
        clearSelection,
        downloadSelectionAsZip,
        values,
        set,
        setMany,
        reset,
        hasFilters,
        folderUrl,
    };
}

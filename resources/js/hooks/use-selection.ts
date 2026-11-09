import { router } from '@inertiajs/react';
import { type MouseEvent, useEffect, useRef, useState } from 'react';

export type SelectableKind = 'file' | 'folder';

export interface SelectableRow {
    kind: SelectableKind;
    id: number;
    /** Whether this person may delete it. Remembered as the selection grows, so bulk delete knows about rows on other pages. */
    deletable?: boolean;
}

interface Carried {
    resetKey: string;
    files: Set<number>;
    folders: Set<number>;
    deletable: Set<number>;
}

/**
 * Selections kept outside the page component, one per list. Every Inertia
 * page change mounts the page afresh, and this is what lets a selection
 * outlive it. A selection is only picked up again under the same reset key
 * (same folder, search and filters), so a new context always starts empty.
 */
const carried = new Map<string, Carried>();

const same = (a: SelectableRow, b: SelectableRow) => a.kind === b.kind && a.id === b.id;

/**
 * The selection on a paginated list of files and folders.
 *
 * - It survives page changes and page-size changes. A new folder, search,
 *   filter or sort (a different `resetKey`) clears it.
 * - Shift-click selects, or clears, every row between the last row clicked
 *   and this one, in the order they are on screen. The clicked row decides
 *   which: ticking it ticks the range, unticking it unticks the range.
 * - `pageState` is what the header checkbox shows for the rows on screen.
 *
 * Leaving the list, or any request that changes data (a bulk edit, a
 * delete), drops the carried selection, because the rows it names may no
 * longer exist.
 *
 * @param scope   a name for this list, unique across the app
 * @param resetKey everything that changes which rows the list holds, except the page and the page size
 * @param rows    the rows on screen, in the order they are shown
 */
export function useSelection({ scope, resetKey, rows }: { scope: string; resetKey: string; rows: SelectableRow[] }) {
    const initial = carried.get(scope)?.resetKey === resetKey ? carried.get(scope) : undefined;
    const [files, setFiles] = useState<Set<number>>(() => initial?.files ?? new Set());
    const [folders, setFolders] = useState<Set<number>>(() => initial?.folders ?? new Set());
    const [deletable, setDeletable] = useState<Set<number>>(() => initial?.deletable ?? new Set());
    const anchor = useRef<SelectableRow | null>(null);

    // Filters applied with preserveState keep the component mounted, so a
    // new reset key has to clear the selection here as well.
    const resetKeyRef = useRef(resetKey);
    useEffect(() => {
        if (resetKeyRef.current === resetKey) return;
        resetKeyRef.current = resetKey;
        anchor.current = null;
        setFiles(new Set());
        setFolders(new Set());
        setDeletable(new Set());
    }, [resetKey]);

    useEffect(() => {
        if (files.size === 0 && folders.size === 0) carried.delete(scope);
        else carried.set(scope, { resetKey, files, folders, deletable });
    }, [scope, resetKey, files, folders, deletable]);

    useEffect(
        () =>
            router.on('before', (event) => {
                const { method, url } = event.detail.visit;
                if (method !== 'get' || url.pathname !== window.location.pathname) carried.delete(scope);
            }),
        [scope],
    );

    const isSelected = (row: SelectableRow) => (row.kind === 'file' ? files : folders).has(row.id);

    const apply = (targets: SelectableRow[], select: boolean) => {
        const update = (current: Set<number>, kind: SelectableKind) => {
            const next = new Set(current);
            for (const row of targets) {
                if (row.kind !== kind) continue;
                if (select) next.add(row.id);
                else next.delete(row.id);
            }
            return next;
        };
        setFiles((current) => update(current, 'file'));
        setFolders((current) => update(current, 'folder'));
        if (select) {
            setDeletable((current) => {
                const next = new Set(current);
                for (const row of targets) if (row.kind === 'file' && row.deletable) next.add(row.id);
                return next;
            });
        }
    };

    const click = (row: SelectableRow, shiftKey: boolean) => {
        const select = !isSelected(row);
        const from = anchor.current ? rows.findIndex((candidate) => same(candidate, anchor.current!)) : -1;
        const to = rows.findIndex((candidate) => same(candidate, row));
        anchor.current = row;

        if (shiftKey && from !== -1 && to !== -1) {
            apply(rows.slice(Math.min(from, to), Math.max(from, to) + 1), select);
        } else {
            apply([row], select);
        }
    };

    /**
     * Spread onto a row's <Checkbox>. It uses onClick, not onCheckedChange,
     * because only the click event knows whether Shift was held.
     */
    const checkboxProps = (row: SelectableRow) => ({
        checked: isSelected(row),
        onClick: (event: MouseEvent) => click(row, event.shiftKey),
        // Shift-click otherwise extends the page's text selection.
        onMouseDown: (event: MouseEvent) => {
            if (event.shiftKey) event.preventDefault();
        },
    });

    const selectedOnPage = rows.filter(isSelected).length;
    const pageState: boolean | 'indeterminate' =
        rows.length > 0 && selectedOnPage === rows.length ? true : selectedOnPage > 0 ? 'indeterminate' : false;

    const togglePage = (checked: boolean) => apply(rows, checked);

    /** Replaces the whole selection, e.g. with every row across all pages. */
    const replace = (ids: { fileIds: number[]; folderIds: number[]; deletableFileIds?: number[] }) => {
        setFiles(new Set(ids.fileIds));
        setFolders(new Set(ids.folderIds));
        setDeletable(new Set(ids.deletableFileIds ?? []));
    };

    const clear = () => {
        anchor.current = null;
        carried.delete(scope);
        setFiles(new Set());
        setFolders(new Set());
        setDeletable(new Set());
    };

    return {
        fileIds: files,
        folderIds: folders,
        count: files.size + folders.size,
        /** The selected files this person may delete, as far as any page shown so far has said. The server checks each one again. */
        deletableFileIds: [...files].filter((id) => deletable.has(id)),
        isSelected,
        click,
        checkboxProps,
        pageState,
        togglePage,
        replace,
        clear,
    };
}

export type Selection = ReturnType<typeof useSelection>;

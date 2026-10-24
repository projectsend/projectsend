import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Download, FileWarning, HardDrive, Loader2, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useFormatDate } from '@/hooks/use-format-date';
import { useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/format-bytes';

interface OrphanRow {
    disk: string;
    path: string;
    size: number;
    last_modified: number;
    allowed: boolean;
}

interface ScannedDisk {
    label: string;
    location: string;
}

interface MissingRow {
    id: number;
    name: string;
    original_name: string;
    size: number;
    disk: string;
    path: string;
    uploader: string | null;
    created_at: string | null;
}

/** The background "Import all" run — see OrphanImportProgress. */
interface ImportRun {
    status: 'running' | 'stalled' | 'finished' | 'failed';
    total: number;
    imported: number;
    error: string | null;
    started_at: number;
}

interface OrphanFilesProps {
    /** Which half of the disk/database mismatch is on screen. */
    tab: 'orphans' | 'missing';
    orphans: OrphanRow[];
    pagination: PaginationMeta;
    search: string;
    scanned_disks: Record<string, ScannedDisk>;
    /** Rows whose bytes are gone — only sent for the missing tab. */
    missing?: MissingRow[];
    missing_count: number;
    import_run: ImportRun | null;
}

/** Often enough to feel live, rarely enough not to matter. */
const POLL_INTERVAL_MS = 3000;

/** disk+path is the real identity — the same relative path can exist independently on more than one scanned disk. */
const itemKey = (disk: string, path: string) => `${disk}\u0000${path}`;

export default function OrphanFiles({ tab, orphans, pagination, search, scanned_disks, missing = [], missing_count, import_run }: OrphanFilesProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const [selected, setSelected] = useState<Set<string>>(new Set());
    // Every orphan the search matches, across all pages — the server
    // rebuilds that list itself, so no paths are sent for it.
    const [allMatching, setAllMatching] = useState(false);

    const [run, setRun] = useState(import_run);
    const [dismissedRun, setDismissedRun] = useState<number | null>(null);
    const importing = run?.status === 'running';

    useEffect(() => setRun(import_run), [import_run]);

    // Follow a background run until it ends, then reload the list: the
    // files it adopted are no longer orphans.
    useEffect(() => {
        if (!importing) return;

        let stopped = false;
        const id = window.setInterval(() => {
            fetch(route('orphan-files.import-status'), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((next: ImportRun | Record<string, never>) => {
                    if (stopped) return;
                    const current = 'status' in next ? (next as ImportRun) : null;
                    setRun(current);
                    if (current?.status !== 'running') router.reload({ only: ['orphans', 'pagination', 'import_run'] });
                })
                .catch(() => {});
        }, POLL_INTERVAL_MS);

        return () => {
            stopped = true;
            window.clearInterval(id);
        };
    }, [importing]);

    const { values, set, reset, hasFilters } = useListQuery('orphan-files.index', { search }, { search: '' });

    // A new search term or page means a different set of rows on screen —
    // stale selections would silently act on paths no longer listed.
    useEffect(() => {
        setSelected(new Set());
        setAllMatching(false);
    }, [search, pagination.page]);

    const toggle = (key: string) => {
        setAllMatching(false);
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    };

    const allSelected = orphans.length > 0 && selected.size === orphans.length;
    const toggleAll = () => {
        setAllMatching(false);
        setSelected(allSelected ? new Set() : new Set(orphans.map((row) => itemKey(row.disk, row.path))));
    };
    const importAllMatching = () => router.post(route('orphan-files.import'), { all: true, search }, { preserveScroll: true });

    const submit = (routeName: string, items: { disk: string; path: string }[]) => router.post(route(routeName), { items }, { preserveScroll: true });
    const importItems = (items: { disk: string; path: string }[]) => submit('orphan-files.import', items);
    const deleteItems = (items: { disk: string; path: string }[]) => submit('orphan-files.delete', items);

    // A 0-byte file is virtually certain to be a failed or interrupted
    // write, not real content — never offered for import, only deletion.
    const canImport = (row: OrphanRow) => !importing && row.allowed && row.size > 0;
    const importableSelected = [...selected]
        .map((key) => orphans.find((row) => itemKey(row.disk, row.path) === key))
        .filter((row): row is OrphanRow => row !== undefined && canImport(row))
        .map((row) => ({ disk: row.disk, path: row.path }));

    const selectedItems = () =>
        [...selected]
            .map((key) => orphans.find((row) => itemKey(row.disk, row.path) === key))
            .filter((row): row is OrphanRow => row !== undefined)
            .map((row) => ({ disk: row.disk, path: row.path }));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Files'), href: '/files' },
        { title: t('Import orphan files'), href: '/files/orphans' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Import orphan files')} />

            <div className="px-4 py-6">
                <Heading
                    title={tab === 'missing' ? t('Files missing from storage') : t('Import orphan files')}
                    description={
                        tab === 'missing'
                            ? t('Files this installation lists and cannot produce. Nobody can download them.')
                            : t(
                                  'Files sitting on disk with no matching record — from an interrupted upload, a restore, or manual filesystem access.',
                              )
                    }
                />

                {/* The same fault seen from either end: bytes with no row,
                    and rows with no bytes. */}
                <div className="border-border mb-4 flex gap-1 border-b">
                    {[
                        { key: 'orphans' as const, label: t('On disk, not in the library') },
                        { key: 'missing' as const, label: t('In the library, not on disk') },
                    ].map(({ key, label }) => (
                        <Link
                            key={key}
                            href={route('orphan-files.index', key === 'missing' ? { tab: 'missing' } : {})}
                            preserveScroll
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${
                                tab === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'
                            }`}
                        >
                            {label}
                            {key === 'missing' && missing_count > 0 && (
                                <span className="text-muted-foreground ml-1.5 text-xs">({missing_count})</span>
                            )}
                        </Link>
                    ))}
                </div>

                {tab === 'missing' && (
                    <div className="space-y-4">
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Checked once a day. A file turns up here when its bytes are gone from storage — a volume remounted elsewhere, a restore without its files, or something deleting them outside ProjectSend. If the storage comes back, so does the file, on the next check.',
                            )}
                        </p>

                        <TableShell
                            columns={[t('File'), t('Uploaded by'), t('Where it should be'), null]}
                            isEmpty={missing.length === 0}
                            emptyMessage={<>{t('Every file in the library is on disk.')}</>}
                        >
                            {missing.map((file) => (
                                <tr key={file.id} className="border-b last:border-0">
                                    <td className="px-4 py-2.5">
                                        <Link href={route('files.edit', file.id)} className="font-medium hover:underline">
                                            {file.name}
                                        </Link>
                                        <div className="text-muted-foreground text-xs">
                                            {file.original_name} · {formatBytes(file.size)}
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-2.5">{file.uploader ?? t('(deleted account)')}</td>
                                    <td className="text-muted-foreground px-4 py-2.5 font-mono text-xs">{file.path}</td>
                                    <td className="px-4 py-2.5 text-right">
                                        <ConfirmDialog
                                            trigger={
                                                <Button type="button" variant="outline" size="sm">
                                                    {t('Remove from the library')}
                                                </Button>
                                            }
                                            title={t('Remove ":name"?', { name: file.name })}
                                            description={t(
                                                'The file itself is already gone. This removes the record of it, and with it the entry every client and every share link points at.',
                                            )}
                                            confirmLabel={t('Remove from the library')}
                                            onConfirm={() => router.delete(route('files.destroy', file.id), { preserveScroll: true })}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </TableShell>

                        <Pagination meta={pagination} />
                    </div>
                )}

                {tab === 'orphans' && (
                <>

                {run && run.started_at !== dismissedRun && (
                    <ImportRunAlert run={run} onDismiss={() => setDismissedRun(run.started_at)} />
                )}

                <div className="text-muted-foreground mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    <span className="font-medium">{t('Scanning:')}</span>
                    {Object.entries(scanned_disks).map(([disk, meta]) => (
                        <span key={disk} className="inline-flex items-center gap-1.5">
                            <HardDrive className="size-3.5 shrink-0" />
                            {t(meta.label)} — <span className="font-mono">{meta.location}</span>
                        </span>
                    ))}
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Search')} htmlFor="orphans-search">
                        <Input
                            id="orphans-search"
                            type="search"
                            placeholder={t('Search by path')}
                            className="w-80"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>
                </ListToolbar>

                {orphans.length === 0 ? (
                    <p className="text-muted-foreground rounded-lg border px-4 py-10 text-center text-sm">
                        {hasFilters ? t('No orphan files match your search.') : t('No orphan files found.')}
                    </p>
                ) : (
                    <>
                        {selected.size > 0 && (
                            <div className="bg-muted/40 mb-3 flex items-center justify-between gap-3 rounded-lg border px-4 py-2">
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                    <p className="font-medium">
                                        {t(':count selected', { count: allMatching ? pagination.total : selected.size })}
                                    </p>
                                    {allSelected && !allMatching && pagination.total > orphans.length && (
                                        <Button variant="link" size="sm" className="h-auto p-0" onClick={() => setAllMatching(true)}>
                                            {t('Select all :count matching files', { count: pagination.total })}
                                        </Button>
                                    )}
                                    {allMatching && (
                                        <span className="text-muted-foreground text-xs">{t('Restricted and empty files are skipped.')}</span>
                                    )}
                                </div>
                                {allMatching && !importing && (
                                    <Button size="sm" onClick={importAllMatching}>
                                        <Download className="size-4" />
                                        {t('Import all')}
                                    </Button>
                                )}
                                {!allMatching && (
                                <div className="flex items-center gap-2">
                                    {importableSelected.length > 0 && (
                                        <Button size="sm" onClick={() => importItems(importableSelected)}>
                                            <Download className="size-4" />
                                            {t('Import selected')}
                                        </Button>
                                    )}
                                    <ConfirmDialog
                                        trigger={
                                            <Button size="sm" variant="outline" className="text-destructive hover:text-destructive">
                                                <Trash2 className="size-4" />
                                                {t('Delete selected')}
                                            </Button>
                                        }
                                        title={t('Delete selected files?')}
                                        description={t('These files will be permanently removed from disk. This cannot be undone.')}
                                        confirmLabel={t('Delete files')}
                                        onConfirm={() => deleteItems(selectedItems())}
                                    />
                                </div>
                                )}
                            </div>
                        )}

                        <div className="relative overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 border-b text-left">
                                    <tr>
                                        <th className="w-8 px-2 py-2.5">
                                            <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label={t('Select All')} />
                                        </th>
                                        <th className="px-4 py-2.5 font-medium">{t('Path')}</th>
                                        <th className="px-4 py-2.5 font-medium whitespace-nowrap">{t('Storage')}</th>
                                        <th className="px-4 py-2.5 font-medium whitespace-nowrap">{t('Size')}</th>
                                        <th className="px-4 py-2.5 font-medium whitespace-nowrap">{t('Last modified')}</th>
                                        <th className="px-4 py-2.5 text-right font-medium">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orphans.map((row) => {
                                        const key = itemKey(row.disk, row.path);

                                        return (
                                            <tr key={key} className="border-b last:border-0">
                                                <td className="px-2 py-2.5">
                                                    <Checkbox
                                                        checked={selected.has(key)}
                                                        onCheckedChange={() => toggle(key)}
                                                        aria-label={t('Select :name', { name: row.path })}
                                                    />
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="flex items-center gap-2">
                                                        <FileWarning className="text-muted-foreground size-4 shrink-0" />
                                                        <span className="break-all">{row.path}</span>
                                                        {!row.allowed && (
                                                            <Badge variant="outline" className="text-amber-600 dark:text-amber-500">
                                                                <AlertTriangle className="size-3" />
                                                                {t('Restricted extension')}
                                                            </Badge>
                                                        )}
                                                        {row.size === 0 && (
                                                            <Badge variant="outline" className="text-amber-600 dark:text-amber-500">
                                                                <AlertTriangle className="size-3" />
                                                                {t('Empty file')}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="text-muted-foreground px-4 py-2.5 text-xs whitespace-nowrap">
                                                    {scanned_disks[row.disk] ? t(scanned_disks[row.disk].label) : row.disk}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">{formatBytes(row.size)}</td>
                                                <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">
                                                    {/* The only date the server sends as a unix timestamp
                                                        rather than ISO — it comes off the filesystem, not a
                                                        column. Converted here so it goes through the same
                                                        formatter as everything else. */}
                                                    {dateTime(new Date(row.last_modified * 1000).toISOString())}
                                                </td>
                                                <td className="px-4 py-2.5 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canImport(row) && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => importItems([{ disk: row.disk, path: row.path }])}
                                                            >
                                                                {t('Import')}
                                                            </Button>
                                                        )}
                                                        <ConfirmDialog
                                                            trigger={
                                                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                                    {t('Delete')}
                                                                </Button>
                                                            }
                                                            title={t('Delete this file?')}
                                                            description={t('The file will be permanently removed from disk. This cannot be undone.')}
                                                            confirmLabel={t('Delete file')}
                                                            onConfirm={() => deleteItems([{ disk: row.disk, path: row.path }])}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <Pagination meta={pagination} />
                    </>
                )}
                </>
                )}
            </div>
        </AppLayout>
    );
}

/**
 * Where the background "Import all" run is. Running: a progress bar, kept
 * live by the page's poll. Otherwise the outcome, until dismissed.
 */
function ImportRunAlert({ run, onDismiss }: { run: ImportRun; onDismiss: () => void }) {
    const { t } = useTranslation();
    const percent = run.total > 0 ? Math.min(100, Math.round((run.imported / run.total) * 100)) : 0;
    const counts = { imported: run.imported, total: run.total };

    const dismiss = (
        <Button variant="ghost" size="icon" className="absolute top-2 right-2 size-7" onClick={onDismiss} aria-label={t('Dismiss')}>
            <X className="size-4" />
        </Button>
    );

    if (run.status === 'running') {
        return (
            <Alert className="mb-4">
                <Loader2 className="size-4 animate-spin" />
                <AlertTitle>{t('Importing in the background')}</AlertTitle>
                <AlertDescription>
                    <p>{t(':imported of :total files imported. You can leave this page — the import keeps going.', counts)}</p>
                    <div
                        className="bg-muted mt-2 h-2 overflow-hidden rounded-full"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={percent}
                    >
                        <div className="bg-primary h-full transition-[width]" style={{ width: `${percent}%` }} />
                    </div>
                </AlertDescription>
            </Alert>
        );
    }

    if (run.status === 'finished') {
        return (
            <Alert variant="success" className="mb-4 pr-12">
                <CheckCircle2 className="size-4" />
                <AlertTitle>{t('Import finished')}</AlertTitle>
                <AlertDescription>{t(':imported of :total files imported.', counts)}</AlertDescription>
                {dismiss}
            </Alert>
        );
    }

    return (
        <Alert variant={run.status === 'failed' ? 'destructive' : 'warning'} className="mb-4 pr-12">
            <AlertTriangle className="size-4" />
            <AlertTitle>{run.status === 'failed' ? t('The import stopped') : t('The import stopped making progress')}</AlertTitle>
            <AlertDescription>
                <p>
                    {run.status === 'failed'
                        ? t(':imported of :total files were imported before it failed: :error', { ...counts, error: run.error ?? '' })
                        : t(':imported of :total files were imported. The queue worker may not be running.', counts)}
                </p>
                <p>{t('Choose Import all again to continue with the rest.')}</p>
            </AlertDescription>
            {dismiss}
        </Alert>
    );
}

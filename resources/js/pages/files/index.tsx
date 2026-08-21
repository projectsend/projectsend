import { type BreadcrumbItem, type SharedData } from '@/types';
import { DndContext, DragEndEvent, DragOverlay, DragStartEvent, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Archive, ChevronRight, ExternalLink, File as FileIcon, Folder as FolderIcon, FolderPlus, Info, Pencil, X } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

import { BulkEditFilesDialog, type BulkEditPayload } from '@/components/bulk-edit-files-dialog';
import { CommentsRowButton } from '@/components/comments/comments-row-button';
import { DownloadAction, DownloadLimitNote } from '@/components/download-action';
import { type DownloadLimit } from '@/types/portal';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DetailsPanel, DetailsTarget } from '@/components/details-panel';
import { DragChip, DragData, DropZone, useFolderDrop, useRowDrag } from '@/components/file-dnd';
import { FilePreviewDialog } from '@/components/file-preview-dialog';
import { PreviewAction } from '@/components/preview-action';
import { VersionBadge, type VersionLinks } from '@/components/files/version-badge';
import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ViewModeToggle } from '@/components/view-mode-toggle';
import { ZipDownloadDialog } from '@/components/zip-download-dialog';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import { useViewMode } from '@/hooks/use-view-mode';
import { useZipDownload } from '@/hooks/use-zip-download';
import AppLayout from '@/layouts/app-layout';
import { categoryColor } from '@/lib/category-colors';
import { formatBytes } from '@/lib/format-bytes';
import { isPreviewable } from '@/lib/previews';
import { isThumbnailable } from '@/lib/thumbnails';
import { slugify } from '@/lib/utils';

interface FolderRow {
    id: number;
    name: string;
    public: boolean;
    public_url: string | null;
    children_count: number;
    files_count: number;
    shared_count: number;
    can_update: boolean;
    can_delete: boolean;
}

interface FileRow {
    id: number;
    name: string;
    original_name: string;
    mime_type: string;
    size: number;
    uploader: { name: string; type: 'staff' | 'client'; role: string | null } | null;
    public: boolean;
    public_url: string | null;
    expired: boolean;
    assignments_count: number;
    downloads_count: number;
    /**
     * The cap on this file as it applies to the viewing staff member —
     * already decided server-side, including their exemption when they
     * uploaded it themselves.
     */
    download_limit: DownloadLimit;
    comments_count: number;
    /** Only ever non-zero for a moderator. */
    pending_comments_count: number;
    created_at: string | null;
    /** Already narrowed to what this viewer may be told; nulls mean "no link, or not yours to know". */
    version: VersionLinks;
    can_update: boolean;
    can_delete: boolean;
    categories: CategoryTag[];
}

interface Crumb {
    id: number;
    name: string;
}

interface CategoryTag {
    id: number;
    name: string;
    color: string;
}

interface FilesIndexProps {
    folder: Crumb | null;
    breadcrumb: Crumb[];
    folders: FolderRow[];
    files: FileRow[];
    pagination: PaginationMeta;
    search: string;
    searching: boolean;
    category: number | null;
    categories: CategoryTag[];
    expired: boolean;
    can_create_folders: boolean;
    can_upload: boolean;
    can_manage_public: boolean;
    comments_enabled: boolean;
    folder_options: Crumb[];
}

export default function FilesIndex({
    folder,
    breadcrumb,
    folders,
    files,
    pagination,
    search,
    searching,
    category,
    categories,
    expired,
    can_create_folders,
    can_upload,
    can_manage_public,
    comments_enabled,
    folder_options,
}: FilesIndexProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;
    const canSetCategories = auth.permissions.includes('set_file_categories');
    const canSetExpiration = auth.permissions.includes('set_file_expiration_date');
    const canLimitDownloads = auth.permissions.includes('limit_downloads');
    const { viewMode, setViewMode } = useViewMode();
    const [newFolderOpen, setNewFolderOpen] = useState(false);
    const [panelTarget, setPanelTarget] = useState<DetailsTarget | null>(null);

    const zip = useZipDownload();
    const [selectedFileIds, setSelectedFileIds] = useState<Set<number>>(new Set());
    const [selectedFolderIds, setSelectedFolderIds] = useState<Set<number>>(new Set());
    const selectionCount = selectedFileIds.size + selectedFolderIds.size;

    // A new folder/search/category context (or a different page) means a
    // different set of rows on screen — stale selections would silently
    // zip items no longer visible.
    useEffect(() => {
        setSelectedFileIds(new Set());
        setSelectedFolderIds(new Set());
    }, [folder?.id, search, category, expired, pagination.page]);

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

    const bulkEditFiles = (payload: BulkEditPayload) =>
        router.patch(
            route('files.bulk-update'),
            { file_ids: [...selectedFileIds], ...payload },
            { preserveScroll: true, preserveState: false, onSuccess: () => clearSelection() },
        );

    // A search term or a category filter both switch to a flat, global view,
    // dropping the current folder context.
    const { values, set, reset, hasFilters } = useListQuery(
        'files.index',
        { search, category: category === null ? ALL : String(category), expired: expired ? 'true' : '' },
        { search: '', category: ALL, expired: '' },
    );

    // A small drag threshold so clicking action buttons never starts a drag.
    // While searching, results are flat/global, so dragging (which reparents
    // relative to the current folder) is disabled by passing no sensors.
    const dragSensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
    const sensors = searching ? [] : dragSensors;

    // dnd-kit doesn't cancel the click that fires on pointer-up after a drag,
    // so dropping onto a folder/breadcrumb <Link> would also navigate to it.
    // We arm a one-shot capture that swallows that trailing click. A plain
    // click never crosses the 6px threshold, so onDragStart never fires and
    // normal navigation is untouched.
    const suppressNextClick = useRef(false);
    const [activeDrag, setActiveDrag] = useState<DragData | null>(null);
    useEffect(() => {
        const swallow = (e: MouseEvent) => {
            if (!suppressNextClick.current) return;
            e.preventDefault();
            e.stopPropagation();
            suppressNextClick.current = false;
        };
        document.addEventListener('click', swallow, true);
        return () => document.removeEventListener('click', swallow, true);
    }, []);

    const onDragStart = (event: DragStartEvent) => {
        suppressNextClick.current = true;
        setActiveDrag((event.active.data.current as DragData | undefined) ?? null);
    };

    const onDragEnd = (event: DragEndEvent) => {
        setActiveDrag(null);
        // Fallback: if no trailing click lands (dropped on empty space), clear
        // the guard so the next genuine click isn't eaten.
        window.setTimeout(() => {
            suppressNextClick.current = false;
        }, 300);

        const dragged = event.active.data.current as { kind: 'file' | 'folder'; id: number } | undefined;
        const dropFolderId = (event.over?.data.current as { folderId: number | null } | undefined)?.folderId;
        if (!dragged || event.over === null || dropFolderId === undefined) return;

        if (dragged.kind === 'file') {
            router.patch(route('files.move', dragged.id), { folder_id: dropFolderId }, { preserveScroll: true, preserveState: false });
        } else if (dragged.id !== dropFolderId) {
            router.patch(route('folders.move', dragged.id), { parent_id: dropFolderId }, { preserveScroll: true, preserveState: false });
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Files'), href: '/files' }];
    const folderUrl = (id: number | null) => (id === null ? route('files.index') : route('files.index', { folder: id }));

    const createForm = useForm({
        name: '',
        parent_id: folder?.id ?? null,
        public: false as boolean,
        slug: '',
        allow_client_uploads: false as boolean,
    });
    const [createSlugTouched, setCreateSlugTouched] = useState(false);
    const createFolder: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('folders.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset('name', 'public', 'slug', 'allow_client_uploads');
                setCreateSlugTouched(false);
                setNewFolderOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Files')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Files')} description={t('Your shared file library')} />
                    <div className="flex gap-2">
                        {folder !== null && !searching && (
                            <Button variant="outline" onClick={() => zip.start({ folder_ids: [folder.id] })}>
                                <Archive className="size-4" />
                                {t('Download as zip')}
                            </Button>
                        )}
                        {can_create_folders && !can_upload && (
                            <TooltipProvider delayDuration={0}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span>
                                            <Button variant="outline" disabled>
                                                <FolderPlus className="size-4" />
                                                {t('New folder')}
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>{t('You need upload permission to create folders')}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}
                        {can_create_folders && can_upload && (
                            <Dialog open={newFolderOpen} onOpenChange={setNewFolderOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        <FolderPlus className="size-4" />
                                        {t('New folder')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>{t('New folder')}</DialogTitle>
                                    </DialogHeader>
                                    <form onSubmit={createFolder} className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="folder-name">{t('Name')}</Label>
                                            <Input
                                                id="folder-name"
                                                value={createForm.data.name}
                                                onChange={(e) => {
                                                    createForm.setData('name', e.target.value);
                                                    if (!createSlugTouched) createForm.setData('slug', slugify(e.target.value));
                                                }}
                                                autoFocus
                                                required
                                            />
                                        </div>

                                        {can_manage_public && (
                                            <div className="grid gap-2">
                                                <div className="flex items-start gap-2">
                                                    <Checkbox
                                                        id="new-folder-public"
                                                        checked={createForm.data.public}
                                                        onCheckedChange={(checked) => createForm.setData('public', checked === true)}
                                                    />
                                                    <Label htmlFor="new-folder-public" className="font-normal">
                                                        {t(
                                                            'Make this folder public. Every file inside it — now and later, at any depth — becomes public too.',
                                                        )}
                                                    </Label>
                                                </div>

                                                {createForm.data.public && (
                                                    <div className="mt-2 grid gap-4 pl-6">
                                                        <div className="grid gap-2">
                                                            <Label htmlFor="new-folder-slug">{t('URL slug')}</Label>
                                                            <Input
                                                                id="new-folder-slug"
                                                                value={createForm.data.slug}
                                                                onChange={(e) => {
                                                                    setCreateSlugTouched(true);
                                                                    createForm.setData('slug', e.target.value);
                                                                }}
                                                                required
                                                            />
                                                        </div>

                                                        <div className="flex items-start gap-2">
                                                            <Checkbox
                                                                id="new-folder-allow-client-uploads"
                                                                checked={createForm.data.allow_client_uploads}
                                                                onCheckedChange={(checked) =>
                                                                    createForm.setData('allow_client_uploads', checked === true)
                                                                }
                                                            />
                                                            <Label htmlFor="new-folder-allow-client-uploads" className="font-normal">
                                                                {t('Allow clients to add files to this folder from their portal.')}
                                                            </Label>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        <DialogFooter>
                                            <Button type="submit" disabled={createForm.processing}>
                                                {t('Create folder')}
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                        {can_upload && (
                            <Button asChild>
                                <Link href={route('files.create')}>{t('Upload')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Search')} htmlFor="files-search">
                        <Input
                            id="files-search"
                            type="search"
                            placeholder={t('Search all files and folders')}
                            className="w-80"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>
                    {categories.length > 0 && (
                        <FilterField label={t('Category')} htmlFor="files-category">
                            <Select value={values.category} onValueChange={(v) => set('category', v)}>
                                <SelectTrigger id="files-category" className="w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>{t('All categories')}</SelectItem>
                                    {categories.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            <span className="flex items-center gap-2">
                                                <span className={`size-2 shrink-0 rounded-full ${categoryColor(c.color).swatch}`} />
                                                {c.name}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FilterField>
                    )}
                    <FilterField label={t('Expired')} htmlFor="files-expired">
                        <div className="flex h-9 items-center gap-2">
                            <Checkbox
                                id="files-expired"
                                checked={values.expired === 'true'}
                                onCheckedChange={(checked) => set('expired', checked === true ? 'true' : '')}
                            />
                            <Label htmlFor="files-expired" className="text-muted-foreground font-normal">
                                {t('Expired only')}
                            </Label>
                        </div>
                    </FilterField>
                </ListToolbar>

                <div className="mb-3 flex justify-end">
                    <ViewModeToggle value={viewMode} onChange={setViewMode} />
                </div>

                <DndContext sensors={sensors} onDragStart={onDragStart} onDragEnd={onDragEnd} onDragCancel={() => setActiveDrag(null)}>
                    {searching ? (
                        <p className="text-muted-foreground mb-3 text-sm">{t('Showing matches from across your library')}</p>
                    ) : (
                        <nav className="text-muted-foreground mb-3 flex flex-wrap items-center gap-1 text-sm">
                            <DropZone folderId={null} className="px-1">
                                <Link href={folderUrl(null)} className="hover:text-foreground">
                                    {t('Library')}
                                </Link>
                            </DropZone>
                            {breadcrumb.map((crumb, index) => (
                                <span key={crumb.id} className="flex items-center gap-1">
                                    <ChevronRight className="size-3.5" />
                                    {index === breadcrumb.length - 1 ? (
                                        <span className="text-foreground">{crumb.name}</span>
                                    ) : (
                                        <DropZone folderId={crumb.id} className="px-1">
                                            <Link href={folderUrl(crumb.id)} className="hover:text-foreground">
                                                {crumb.name}
                                            </Link>
                                        </DropZone>
                                    )}
                                </span>
                            ))}
                        </nav>
                    )}

                    {selectionCount > 0 && (
                        <div className="bg-muted/40 mb-3 flex items-center justify-between gap-3 rounded-lg border px-4 py-2">
                            <p className="text-sm font-medium">{t(':count selected', { count: selectionCount })}</p>
                            <div className="flex items-center gap-2">
                                <Button size="sm" onClick={downloadSelectionAsZip}>
                                    <Archive className="size-4" />
                                    {t('Download as zip')}
                                </Button>
                                {selectedFileIds.size === 0 ? (
                                    <TooltipProvider delayDuration={0}>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span>
                                                    <Button size="sm" variant="outline" disabled>
                                                        <Pencil className="size-4" />
                                                        {t('Edit')}
                                                    </Button>
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>{t('Select files to bulk-edit them')}</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                ) : (
                                    <BulkEditFilesDialog
                                        trigger={
                                            <Button size="sm" variant="outline">
                                                <Pencil className="size-4" />
                                                {t('Edit')}
                                            </Button>
                                        }
                                        count={selectedFileIds.size}
                                        folderOptions={folder_options}
                                        categories={categories}
                                        canSetCategories={canSetCategories}
                                        canSetExpiration={canSetExpiration}
                                        canLimitDownloads={canLimitDownloads}
                                        onConfirm={bulkEditFiles}
                                    />
                                )}
                                <Button variant="ghost" size="sm" onClick={clearSelection}>
                                    <X className="size-4" />
                                    {t('Clear')}
                                </Button>
                            </div>
                        </div>
                    )}

                    {folders.length === 0 && files.length === 0 && (
                        <p className="text-muted-foreground rounded-lg border px-4 py-10 text-center text-sm">
                            {searching ? t('No files or folders match your search.') : t('This folder is empty.')}
                        </p>
                    )}

                    {viewMode === 'list' ? (
                        (folders.length > 0 || files.length > 0) && (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <tbody>
                                        {folders.map((row) => (
                                            <FolderRow
                                                key={`folder-${row.id}`}
                                                row={row}
                                                folderUrl={folderUrl}
                                                onDetails={() => setPanelTarget({ type: 'folder', id: row.id })}
                                                selected={selectedFolderIds.has(row.id)}
                                                onToggleSelect={() => toggleFolder(row.id)}
                                            />
                                        ))}
                                        {files.map((row) => (
                                            <FileRow
                                                key={`file-${row.id}`}
                                                row={row}
                                                onDetails={() => setPanelTarget({ type: 'file', id: row.id })}
                                                onComments={
                                                    comments_enabled ? () => setPanelTarget({ type: 'file', id: row.id, tab: 'comments' }) : undefined
                                                }
                                                selected={selectedFileIds.has(row.id)}
                                                onToggleSelect={() => toggleFile(row.id)}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )
                    ) : (
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            {folders.map((row) => (
                                <FolderCard
                                    key={`folder-${row.id}`}
                                    row={row}
                                    folderUrl={folderUrl}
                                    onDetails={() => setPanelTarget({ type: 'folder', id: row.id })}
                                    selected={selectedFolderIds.has(row.id)}
                                    onToggleSelect={() => toggleFolder(row.id)}
                                />
                            ))}
                            {files.map((row) => (
                                <FileCard
                                    key={`file-${row.id}`}
                                    row={row}
                                    onDetails={() => setPanelTarget({ type: 'file', id: row.id })}
                                    onComments={comments_enabled ? () => setPanelTarget({ type: 'file', id: row.id, tab: 'comments' }) : undefined}
                                    selected={selectedFileIds.has(row.id)}
                                    onToggleSelect={() => toggleFile(row.id)}
                                />
                            ))}
                        </div>
                    )}

                    <DragOverlay dropAnimation={null}>{activeDrag && <DragChip data={activeDrag} />}</DragOverlay>
                </DndContext>

                <Pagination meta={pagination} />

                {!searching && (
                    <p className="text-muted-foreground mt-2 text-xs">
                        {t('Tip: drag files and folders onto a folder or a breadcrumb to move them.')}
                    </p>
                )}
            </div>

            {panelTarget && <DetailsPanel target={panelTarget} onClose={() => setPanelTarget(null)} />}
            <ZipDownloadDialog status={zip.status} error={zip.error} onClose={zip.close} />
        </AppLayout>
    );
}

// The whole row is the drag handle (and, for folders, the drop target). A 6px
// activation distance keeps clicks on the title/action buttons from starting a
// drag; the dimmed source row plus the floating DragChip show what's moving.
const rowBase = 'group hover:bg-muted/40 cursor-grab border-b select-none last:border-0 active:cursor-grabbing';

function FolderRow({
    row,
    folderUrl,
    onDetails,
    selected,
    onToggleSelect,
}: {
    row: FolderRow;
    folderUrl: (id: number | null) => string;
    onDetails: () => void;
    selected: boolean;
    onToggleSelect: () => void;
}) {
    const { t } = useTranslation();
    const { setDragRef, dragHandleProps, isDragging } = useRowDrag({ kind: 'folder', id: row.id, name: row.name });
    const { setDropRef, isOver } = useFolderDrop(row.id);
    const setRef = (node: HTMLTableRowElement | null) => {
        setDragRef(node);
        setDropRef(node);
    };

    return (
        <tr
            ref={setRef}
            {...dragHandleProps}
            className={`${rowBase} ${isDragging ? 'opacity-40' : ''} ${isOver ? 'bg-primary/15 [box-shadow:inset_0_0_0_2px_var(--primary)]' : ''}`}
        >
            <td className="w-8 px-2 py-2.5" onPointerDown={(e) => e.stopPropagation()}>
                <Checkbox checked={selected} onCheckedChange={onToggleSelect} aria-label={t('Select :name', { name: row.name })} />
            </td>
            <td className="px-4 py-2.5">
                <Link href={folderUrl(row.id)} draggable={false} className="inline-flex items-center gap-2 font-medium">
                    <FolderIcon className="text-primary size-5 shrink-0" />
                    {row.name}
                    {row.public && (
                        <Badge variant="secondary" className="text-[11px] font-normal">
                            {t('Public')}
                        </Badge>
                    )}
                </Link>
            </td>
            <td className="text-muted-foreground px-4 py-2.5 text-sm whitespace-nowrap">
                {t(':files files', { files: row.files_count })}
                {row.children_count > 0 && ` · ${t(':count folders', { count: row.children_count })}`}
            </td>
            <td className="text-muted-foreground px-4 py-2.5 text-sm whitespace-nowrap">
                {row.shared_count > 0 && t('Shared with :count', { count: row.shared_count })}
            </td>
            <td className="px-4 py-2.5 text-right">
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="sm" onClick={onDetails}>
                        <Info className="size-4" />
                        <span className="sr-only">{t('Details')}</span>
                    </Button>
                    {row.public_url && (
                        <Button variant="ghost" size="sm" asChild>
                            <a href={row.public_url} target="_blank" rel="noreferrer">
                                <ExternalLink className="size-4" />
                                <span className="sr-only">{t('Open public page')}</span>
                            </a>
                        </Button>
                    )}
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('folders.share', row.id)}>{row.can_update ? t('Edit') : t('View')}</Link>
                    </Button>
                    {row.can_delete && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                    {t('Delete')}
                                </Button>
                            }
                            title={t('Delete folder?')}
                            description={t('The folder ":name" and everything inside it will be deleted. This can be undone by an administrator.', {
                                name: row.name,
                            })}
                            confirmLabel={t('Delete folder')}
                            onConfirm={() => router.delete(route('folders.destroy', row.id), { preserveScroll: true })}
                        />
                    )}
                </div>
            </td>
        </tr>
    );
}

function FileRow({
    row,
    onDetails,
    onComments,
    selected,
    onToggleSelect,
}: {
    row: FileRow;
    onDetails: () => void;
    /** Undefined when the installation has commenting switched off. */
    onComments?: () => void;
    selected: boolean;
    onToggleSelect: () => void;
}) {
    const { t } = useTranslation();
    const { setDragRef, dragHandleProps, isDragging } = useRowDrag({ kind: 'file', id: row.id, name: row.name });

    return (
        <tr ref={setDragRef} {...dragHandleProps} className={`${rowBase} ${isDragging ? 'opacity-40' : ''}`}>
            <td className="w-8 px-2 py-2.5" onPointerDown={(e) => e.stopPropagation()}>
                <Checkbox checked={selected} onCheckedChange={onToggleSelect} aria-label={t('Select :name', { name: row.name })} />
            </td>
            <td className="px-4 py-2.5">
                <div className="flex items-start gap-2">
                    <FilePreviewDialog
                        previewUrl={route('files.preview', row.id)}
                        mimeType={row.mime_type}
                        fileName={row.original_name}
                        className="shrink-0"
                    >
                        {isThumbnailable(row.mime_type) ? (
                            <img src={route('files.thumbnail', row.id)} alt="" className="size-10 rounded border object-cover" draggable={false} />
                        ) : (
                            <FileIcon className="text-muted-foreground mt-0.5 size-10 shrink-0" strokeWidth={1.25} />
                        )}
                    </FilePreviewDialog>
                    <div className="min-w-0">
                        <div className="flex items-center gap-1.5">
                            <Link href={route('files.edit', row.id)} draggable={false} className="font-medium">
                                {row.name}
                            </Link>
                            {row.public && (
                                <Badge variant="secondary" className="text-[11px] font-normal">
                                    {t('Public')}
                                </Badge>
                            )}
                            {row.expired && (
                                <Badge variant="destructive" className="text-[11px] font-normal">
                                    {t('Expired')}
                                </Badge>
                            )}
                            <VersionBadge version={row.version} />
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {row.original_name}
                            {row.uploader && (
                                <>
                                    {' · '}
                                    {row.uploader.name}
                                    {row.uploader.role && <span className="text-[10px] opacity-75"> ({row.uploader.role})</span>}
                                </>
                            )}
                        </p>
                        {row.categories.length > 0 && (
                            <div className="mt-1 flex flex-wrap gap-1">
                                {row.categories.map((category) => (
                                    <Badge
                                        key={category.id}
                                        variant="outline"
                                        className={`text-[11px] font-normal ${categoryColor(category.color).badge}`}
                                    >
                                        {category.name}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </td>
            <td className="text-muted-foreground px-4 py-2.5 text-sm whitespace-nowrap">{formatBytes(row.size)}</td>
            <td className="text-muted-foreground px-4 py-2.5 text-sm whitespace-nowrap">
                {t(':count downloads', { count: row.downloads_count })}
                {row.download_limit.limit !== null && (
                    <>
                        {' · '}
                        <DownloadLimitNote limit={row.download_limit} />
                    </>
                )}
                {row.assignments_count > 0 && ` · ${t('Shared with :count', { count: row.assignments_count })}`}
            </td>
            <td className="px-4 py-2.5 text-right">
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="sm" onClick={onDetails}>
                        <Info className="size-4" />
                        <span className="sr-only">{t('Details')}</span>
                    </Button>
                    {onComments && <CommentsRowButton count={row.comments_count} pending={row.pending_comments_count} onOpen={onComments} />}
                    {row.public_url && (
                        <Button variant="ghost" size="sm" asChild>
                            <a href={row.public_url} target="_blank" rel="noreferrer">
                                <ExternalLink className="size-4" />
                                <span className="sr-only">{t('Open public page')}</span>
                            </a>
                        </Button>
                    )}
                    <PreviewAction
                        previewUrl={route('files.preview', row.id)}
                        mimeType={row.mime_type}
                        fileName={row.original_name}
                        variant="ghost"
                        size="sm"
                        iconOnly
                    />
                    <DownloadAction href={route('files.download', row.id)} limit={row.download_limit} variant="ghost" size="sm" iconOnly />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('files.edit', row.id)}>{row.can_update ? t('Edit') : t('View')}</Link>
                    </Button>
                    {row.can_delete && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                    {t('Delete')}
                                </Button>
                            }
                            title={t('Delete file?')}
                            description={t('The file ":name" will no longer be available to anyone it was shared with.', {
                                name: row.name,
                            })}
                            confirmLabel={t('Delete file')}
                            onConfirm={() => router.delete(route('files.destroy', row.id), { preserveScroll: true })}
                        />
                    )}
                </div>
            </td>
        </tr>
    );
}

function FolderCard({
    row,
    folderUrl,
    onDetails,
    selected,
    onToggleSelect,
}: {
    row: FolderRow;
    folderUrl: (id: number | null) => string;
    onDetails: () => void;
    selected: boolean;
    onToggleSelect: () => void;
}) {
    const { t } = useTranslation();
    const { setDragRef, dragHandleProps, isDragging } = useRowDrag({ kind: 'folder', id: row.id, name: row.name });
    const { setDropRef, isOver } = useFolderDrop(row.id);
    const setRef = (node: HTMLDivElement | null) => {
        setDragRef(node);
        setDropRef(node);
    };

    return (
        <div
            ref={setRef}
            {...dragHandleProps}
            className={`group hover:border-primary/50 relative flex cursor-grab flex-col items-center justify-center gap-2 rounded-xl border p-6 text-center transition select-none hover:shadow-md active:cursor-grabbing ${isDragging ? 'opacity-40' : ''} ${isOver ? 'border-primary bg-primary/10' : ''}`}
        >
            <Checkbox
                checked={selected}
                onCheckedChange={onToggleSelect}
                onPointerDown={(e) => e.stopPropagation()}
                aria-label={t('Select :name', { name: row.name })}
                className="absolute top-3 left-3"
            />
            <Link href={folderUrl(row.id)} draggable={false} className="flex w-full flex-col items-center gap-2">
                <FolderIcon className="text-primary size-10 shrink-0" strokeWidth={1.5} />
                <span className="flex max-w-full items-center gap-1.5">
                    <p className="truncate text-sm font-medium">{row.name}</p>
                    {row.public && (
                        <Badge variant="secondary" className="text-[11px] font-normal">
                            {t('Public')}
                        </Badge>
                    )}
                </span>
                <p className="text-muted-foreground text-xs">
                    {t(':files files', { files: row.files_count })}
                    {row.children_count > 0 && ` · ${t(':count folders', { count: row.children_count })}`}
                </p>
            </Link>
            <div className="bg-background/80 absolute top-2 right-2 flex rounded-md opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100 hover:opacity-100">
                <Button variant="ghost" size="sm" onClick={onDetails}>
                    <Info className="size-4" />
                    <span className="sr-only">{t('Details')}</span>
                </Button>
                {row.public_url && (
                    <Button variant="ghost" size="sm" asChild>
                        <a href={row.public_url} target="_blank" rel="noreferrer">
                            <ExternalLink className="size-4" />
                            <span className="sr-only">{t('Open public page')}</span>
                        </a>
                    </Button>
                )}
                {row.can_delete && (
                    <ConfirmDialog
                        trigger={
                            <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                <X className="size-4" />
                                <span className="sr-only">{t('Delete')}</span>
                            </Button>
                        }
                        title={t('Delete folder?')}
                        description={t('The folder ":name" and everything inside it will be deleted. This can be undone by an administrator.', {
                            name: row.name,
                        })}
                        confirmLabel={t('Delete folder')}
                        onConfirm={() => router.delete(route('folders.destroy', row.id), { preserveScroll: true })}
                    />
                )}
            </div>
        </div>
    );
}

function FileCard({
    row,
    onDetails,
    onComments,
    selected,
    onToggleSelect,
}: {
    row: FileRow;
    onDetails: () => void;
    /** Undefined when the installation has commenting switched off. */
    onComments?: () => void;
    selected: boolean;
    onToggleSelect: () => void;
}) {
    const { t } = useTranslation();
    const { setDragRef, dragHandleProps, isDragging } = useRowDrag({ kind: 'file', id: row.id, name: row.name });

    return (
        <div
            ref={setDragRef}
            {...dragHandleProps}
            className={`group hover:border-primary/50 relative cursor-grab overflow-hidden rounded-xl border transition select-none hover:shadow-lg active:cursor-grabbing ${isDragging ? 'opacity-40' : ''}`}
        >
            <Checkbox
                checked={selected}
                onCheckedChange={onToggleSelect}
                onPointerDown={(e) => e.stopPropagation()}
                aria-label={t('Select :name', { name: row.name })}
                className="bg-background/80 absolute top-3 left-3 z-10"
            />

            {isPreviewable(row.mime_type) ? (
                <FilePreviewDialog
                    previewUrl={route('files.preview', row.id)}
                    mimeType={row.mime_type}
                    fileName={row.original_name}
                    className="bg-muted block aspect-square w-full overflow-hidden"
                >
                    {isThumbnailable(row.mime_type) ? (
                        <img
                            src={route('files.thumbnail', row.id)}
                            alt=""
                            draggable={false}
                            className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center">
                            <FileIcon className="text-muted-foreground size-10" strokeWidth={1.25} />
                        </div>
                    )}
                </FilePreviewDialog>
            ) : (
                <div className="bg-muted flex aspect-square items-center justify-center">
                    <FileIcon className="text-muted-foreground size-10" strokeWidth={1.25} />
                </div>
            )}

            <div className="p-3">
                <div className="flex items-center gap-1.5">
                    <Link href={route('files.edit', row.id)} draggable={false} className="truncate text-sm font-medium">
                        {row.name}
                    </Link>
                    {row.public && (
                        <Badge variant="secondary" className="text-[11px] font-normal">
                            {t('Public')}
                        </Badge>
                    )}
                    {row.expired && (
                        <Badge variant="destructive" className="text-[11px] font-normal">
                            {t('Expired')}
                        </Badge>
                    )}
                    <VersionBadge version={row.version} />
                </div>
                <p className="text-muted-foreground truncate text-xs">
                    {formatBytes(row.size)}
                    {row.uploader && (
                        <>
                            {' · '}
                            {row.uploader.name}
                            {row.uploader.role && <span className="text-[10px] opacity-75"> ({row.uploader.role})</span>}
                        </>
                    )}
                </p>
                {row.categories.length > 0 && (
                    <div className="mt-1 flex flex-wrap gap-1">
                        {row.categories.map((category) => (
                            <Badge key={category.id} variant="outline" className={`text-[11px] font-normal ${categoryColor(category.color).badge}`}>
                                {category.name}
                            </Badge>
                        ))}
                    </div>
                )}
                <div className="mt-2 flex items-center justify-end gap-1">
                    <Button variant="ghost" size="sm" onClick={onDetails}>
                        <Info className="size-4" />
                        <span className="sr-only">{t('Details')}</span>
                    </Button>
                    {onComments && <CommentsRowButton count={row.comments_count} pending={row.pending_comments_count} onOpen={onComments} />}
                    {row.public_url && (
                        <Button variant="ghost" size="sm" asChild>
                            <a href={row.public_url} target="_blank" rel="noreferrer">
                                <ExternalLink className="size-4" />
                                <span className="sr-only">{t('Open public page')}</span>
                            </a>
                        </Button>
                    )}
                    <PreviewAction
                        previewUrl={route('files.preview', row.id)}
                        mimeType={row.mime_type}
                        fileName={row.original_name}
                        variant="ghost"
                        size="sm"
                        iconOnly
                    />
                    <DownloadAction href={route('files.download', row.id)} limit={row.download_limit} variant="ghost" size="sm" iconOnly />
                    {row.can_delete && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                    {t('Delete')}
                                </Button>
                            }
                            title={t('Delete file?')}
                            description={t('The file ":name" will no longer be available to anyone it was shared with.', {
                                name: row.name,
                            })}
                            confirmLabel={t('Delete file')}
                            onConfirm={() => router.delete(route('files.destroy', row.id), { preserveScroll: true })}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}

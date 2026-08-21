import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Archive, File as FileIcon, Folder as FolderIcon, Globe, Upload } from 'lucide-react';

import { CommentsShellDefault } from '@/components/comments/shells/comments-shell-default';
import { DownloadAction } from '@/components/download-action';
import { FilePreviewDialog } from '@/components/file-preview-dialog';
import { PreviewAction } from '@/components/preview-action';
import { CategoryBadges } from '@/components/files/category-badges';
import { VersionBadge } from '@/components/files/version-badge';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { FolderRowActions } from '@/components/portal/folder-row-actions';
import { NewFolderButton } from '@/components/portal/new-folder-button';
import { PortalBreadcrumb } from '@/components/portal/portal-breadcrumb';
import { PortalFilesToolbar } from '@/components/portal/portal-files-toolbar';
import { RenameFolderDialog } from '@/components/portal/rename-folder-dialog';
import { SelectionBar } from '@/components/portal/selection-bar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ViewModeToggle } from '@/components/view-mode-toggle';
import { ZipDownloadDialog } from '@/components/zip-download-dialog';
import { useFolderManagement } from '@/hooks/use-folder-management';
import { useFormatDate } from '@/hooks/use-format-date';
import { usePortalFiles } from '@/hooks/use-portal-files';
import { useTranslation } from '@/hooks/use-translation';
import { useViewMode } from '@/hooks/use-view-mode';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/format-bytes';
import { isPreviewable } from '@/lib/previews';
import { isThumbnailable } from '@/lib/thumbnails';
import { type MyFilesFolderManagementProps } from '@/types/portal';

export default function MyFiles(props: MyFilesFolderManagementProps) {
    const {
        folder,
        breadcrumb,
        folders,
        files,
        pagination,
        searching,
        categories,
        can_upload,
        can_upload_here,
        can_create_folders,
        comments_enabled,
        preview_enabled,
    } = props;

    const { t } = useTranslation();
    // Arrived from a comment notification (see CommentDeepLinkController).
    // Only opens if that file is among the rows on this page — the client
    // still lands somewhere sensible either way.
    const deepLinkedComments = Number(new URLSearchParams(window.location.search).get('comments'));
    const { date } = useFormatDate();
    const { viewMode, setViewMode } = useViewMode();

    const {
        newFolderOpen,
        setNewFolderOpen,
        createForm,
        createFolder,
        renamingFolder,
        setRenamingFolder,
        startRename,
        renameForm,
        renameFolder,
        deleteFolder,
    } = useFolderManagement(folder);

    const {
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
    } = usePortalFiles(props);

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('My files'), href: '/my-files' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('My files')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={folder?.name ?? t('My files')} description={t('The files shared with you')} />
                    <div className="flex items-center gap-2">
                        <NewFolderButton
                            open={newFolderOpen}
                            onOpenChange={setNewFolderOpen}
                            form={createForm}
                            onSubmit={createFolder}
                            canCreate={can_create_folders}
                            canUpload={can_upload}
                            searching={searching}
                        />
                        {can_upload && can_upload_here && (
                            <Button asChild>
                                <Link href={route('my-files.upload.create', folder !== null ? { folder: folder.id } : {})}>
                                    <Upload className="size-4" />
                                    {t('Upload a file')}
                                </Link>
                            </Button>
                        )}
                        {folder !== null && !searching && (
                            <Button variant="outline" onClick={() => zip.start({ folder_ids: [folder.id] })}>
                                <Archive className="size-4" />
                                {t('Download as zip')}
                            </Button>
                        )}
                    </div>
                </div>

                <div>
                    <PortalFilesToolbar categories={categories} values={values} set={set} setMany={setMany} reset={reset} hasFilters={hasFilters} />
                </div>

                {searching ? (
                    <p className="text-muted-foreground mb-3 text-sm">{t('Showing matches from everything shared with you')}</p>
                ) : (
                    <PortalBreadcrumb breadcrumb={breadcrumb} folderUrl={folderUrl} className="mb-3" />
                )}

                <div className="mb-3 flex justify-end">
                    <ViewModeToggle value={viewMode} onChange={setViewMode} />
                </div>

                <SelectionBar count={selectionCount} onDownload={downloadSelectionAsZip} onClear={clearSelection} className="mb-3" />

                {folders.length === 0 && files.length === 0 && (
                    <p className="text-muted-foreground rounded-lg border px-4 py-10 text-center text-sm">
                        {searching ? t('No files or folders match your search.') : t('No files have been shared with you yet.')}
                    </p>
                )}

                {viewMode === 'list' ? (
                    <div className="space-y-2">
                        {folders.map((row) => (
                            <div key={`folder-${row.id}`} className="bg-card flex items-center gap-3 rounded-lg border px-4 py-3">
                                <Checkbox
                                    checked={selectedFolderIds.has(row.id)}
                                    onCheckedChange={() => toggleFolder(row.id)}
                                    aria-label={t('Select :name', { name: row.name })}
                                />
                                <Link href={folderUrl(row.id)} className="hover:bg-accent/40 -m-3 flex flex-1 items-center gap-3 rounded-lg p-3">
                                    <FolderIcon className="text-primary size-5 shrink-0" />
                                    <p className="text-sm font-medium">{row.name}</p>
                                    {row.public && (
                                        <span title={t('Public folder')}>
                                            <Globe className="text-muted-foreground size-3.5 shrink-0" />
                                            <span className="sr-only">{t('Public folder')}</span>
                                        </span>
                                    )}
                                </Link>
                                <FolderRowActions folder={row} onRename={startRename} onDelete={deleteFolder} />
                            </div>
                        ))}

                        {files.map((file) => (
                            <div key={`file-${file.id}`} className="bg-card flex items-center gap-4 rounded-lg border px-4 py-3">
                                {/* flex-1, not justify-between: with three
                                    children the middle one lands wherever
                                    the name block happens to end, so the
                                    comment icon sat at a different place on
                                    every row. The name takes the slack and
                                    the actions are one group at the end. */}
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <Checkbox
                                        checked={selectedFileIds.has(file.id)}
                                        onCheckedChange={() => toggleFile(file.id)}
                                        aria-label={t('Select :name', { name: file.name })}
                                    />
                                    <FilePreviewDialog
                                        previewUrl={preview_enabled ? route('files.preview', file.id) : null}
                                        mimeType={file.mime_type}
                                        fileName={file.original_name}
                                        className="shrink-0"
                                    >
                                        {isThumbnailable(file.mime_type) ? (
                                            <img src={route('files.thumbnail', file.id)} alt="" className="size-10 rounded border object-cover" />
                                        ) : (
                                            <FileIcon className="text-muted-foreground size-10 shrink-0" strokeWidth={1.25} />
                                        )}
                                    </FilePreviewDialog>
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-1.5">
                                            <p className="truncate text-sm font-medium">{file.name}</p>
                                            {file.public && (
                                                <span title={t('Public file')}>
                                                    <Globe className="text-muted-foreground size-3.5 shrink-0" />
                                                    <span className="sr-only">{t('Public file')}</span>
                                                </span>
                                            )}
                                            <VersionBadge version={file.version} variant="default" />
                                        </div>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {file.description ?? file.original_name} · {formatBytes(file.size)} · {date(file.created_at)}
                                        </p>
                                        <CategoryBadges categories={file.categories} className="mt-1" />
                                    </div>
                                </div>
                                {/* One actions group, right-aligned: icons in
                                    fixed-width slots so they form a column
                                    down the list, then the buttons. */}
                                <div className="flex shrink-0 items-center gap-2">
                                    {comments_enabled && (
                                        <div className="flex w-10 shrink-0 justify-center">
                                            <CommentsShellDefault
                                                fileId={file.id}
                                                defaultOpen={deepLinkedComments === file.id}
                                                fileName={file.name}
                                                count={file.comments_count}
                                                unread={file.unread_comments_count}
                                            />
                                        </div>
                                    )}
                                    <PreviewAction
                                        previewUrl={preview_enabled ? route('files.preview', file.id) : null}
                                        mimeType={file.mime_type}
                                        fileName={file.original_name}
                                        variant="ghost"
                                        size="sm"
                                    />
                                    <DownloadAction href={route('files.download', file.id)} limit={file.download_limit} variant="outline" size="sm" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        {folders.map((row) => (
                            <div
                                key={`folder-${row.id}`}
                                className="group hover:border-primary/50 relative flex flex-col items-center justify-center gap-2 rounded-xl border p-6 text-center transition hover:shadow-md"
                            >
                                <Checkbox
                                    checked={selectedFolderIds.has(row.id)}
                                    onCheckedChange={() => toggleFolder(row.id)}
                                    aria-label={t('Select :name', { name: row.name })}
                                    className="absolute top-3 left-3"
                                />
                                <Link href={folderUrl(row.id)} className="flex w-full flex-col items-center gap-2">
                                    <FolderIcon className="text-primary size-10 shrink-0" strokeWidth={1.5} />
                                    <span className="flex max-w-full items-center gap-1.5">
                                        <p className="truncate text-sm font-medium">{row.name}</p>
                                        {row.public && (
                                            <span title={t('Public folder')}>
                                                <Globe className="text-muted-foreground size-3.5 shrink-0" />
                                                <span className="sr-only">{t('Public folder')}</span>
                                            </span>
                                        )}
                                    </span>
                                </Link>
                                {(row.can_update || row.can_delete) && (
                                    <div className="bg-background/80 absolute top-2 right-2 flex rounded-md opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100 hover:opacity-100">
                                        <FolderRowActions folder={row} onRename={startRename} onDelete={deleteFolder} />
                                    </div>
                                )}
                            </div>
                        ))}

                        {files.map((file) => (
                            <div
                                key={`file-${file.id}`}
                                className="group hover:border-primary/50 relative overflow-hidden rounded-xl border transition hover:shadow-lg"
                            >
                                <Checkbox
                                    checked={selectedFileIds.has(file.id)}
                                    onCheckedChange={() => toggleFile(file.id)}
                                    aria-label={t('Select :name', { name: file.name })}
                                    className="bg-background/80 absolute top-3 left-3 z-10"
                                />

                                {preview_enabled && isPreviewable(file.mime_type) ? (
                                    <FilePreviewDialog
                                        previewUrl={route('files.preview', file.id)}
                                        mimeType={file.mime_type}
                                        fileName={file.original_name}
                                        className="bg-muted block aspect-square w-full overflow-hidden"
                                    >
                                        {isThumbnailable(file.mime_type) ? (
                                            <img
                                                src={route('files.thumbnail', file.id)}
                                                alt=""
                                                className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                            />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center">
                                                <FileIcon className="text-muted-foreground size-10" strokeWidth={1.25} />
                                            </div>
                                        )}
                                    </FilePreviewDialog>
                                ) : isThumbnailable(file.mime_type) ? (
                                    <div className="bg-muted aspect-square w-full overflow-hidden">
                                        <img
                                            src={route('files.thumbnail', file.id)}
                                            alt=""
                                            className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                        />
                                    </div>
                                ) : (
                                    <div className="bg-muted flex aspect-square items-center justify-center">
                                        <FileIcon className="text-muted-foreground size-10" strokeWidth={1.25} />
                                    </div>
                                )}

                                <div className="flex items-center justify-between gap-2 p-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-1.5">
                                            <p className="truncate text-sm font-medium">{file.name}</p>
                                            {file.public && (
                                                <span title={t('Public file')}>
                                                    <Globe className="text-muted-foreground size-3.5 shrink-0" />
                                                    <span className="sr-only">{t('Public file')}</span>
                                                </span>
                                            )}
                                            <VersionBadge version={file.version} variant="default" />
                                        </div>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {formatBytes(file.size)} · {date(file.created_at)}
                                        </p>
                                        <CategoryBadges categories={file.categories} className="mt-1" />
                                    </div>
                                    <div className="flex shrink-0 items-center">
                                        {comments_enabled && (
                                            <CommentsShellDefault
                                                fileId={file.id}
                                                defaultOpen={deepLinkedComments === file.id}
                                                fileName={file.name}
                                                count={file.comments_count}
                                                unread={file.unread_comments_count}
                                            />
                                        )}
                                        <PreviewAction
                                            previewUrl={preview_enabled ? route('files.preview', file.id) : null}
                                            mimeType={file.mime_type}
                                            fileName={file.original_name}
                                            variant="ghost"
                                            size="sm"
                                            iconOnly
                                        />
                                        <DownloadAction
                                            href={route('files.download', file.id)}
                                            limit={file.download_limit}
                                            variant="ghost"
                                            size="sm"
                                            iconOnly
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <Pagination meta={pagination} />
            </div>
            <ZipDownloadDialog status={zip.status} error={zip.error} onClose={zip.close} />
            <RenameFolderDialog folder={renamingFolder} onClose={() => setRenamingFolder(null)} form={renameForm} onSubmit={renameFolder} />
        </AppLayout>
    );
}

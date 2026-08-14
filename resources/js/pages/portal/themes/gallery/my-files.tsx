import { Head, Link } from '@inertiajs/react';
import { Archive, File as FileIcon, Folder as FolderIcon, Globe, Upload } from 'lucide-react';

import { CommentsShellGallery } from '@/components/comments/shells/comments-shell-gallery';
import { DownloadAction } from '@/components/download-action';
import { FilePreviewDialog } from '@/components/file-preview-dialog';
import { CategoryBadges } from '@/components/files/category-badges';
import { VersionBadge } from '@/components/files/version-badge';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { FolderRowActions } from '@/components/portal/folder-row-actions';
import { NewFolderButton } from '@/components/portal/new-folder-button';
import { PortalBreadcrumb } from '@/components/portal/portal-breadcrumb';
import { PortalFilesToolbarGallery } from '@/components/portal/portal-files-toolbar-gallery';
import { RenameFolderDialog } from '@/components/portal/rename-folder-dialog';
import { SelectionBar } from '@/components/portal/selection-bar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ZipDownloadDialog } from '@/components/zip-download-dialog';
import { useFolderManagement } from '@/hooks/use-folder-management';
import { useFormatDate } from '@/hooks/use-format-date';
import { usePortalFiles } from '@/hooks/use-portal-files';
import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/format-bytes';
import { isThumbnailable } from '@/lib/thumbnails';
import { type MyFilesFolderManagementProps } from '@/types/portal';

import PortalLayoutGallery from '@/layouts/portal-layout-gallery';

export default function MyFilesGallery(props: MyFilesFolderManagementProps) {
    const { t } = useTranslation();
    // Arrived from a comment notification (see CommentDeepLinkController).
    // Only opens if that file is among the rows on this page — the client
    // still lands somewhere sensible either way.
    const deepLinkedComments = Number(new URLSearchParams(window.location.search).get('comments'));
    const { date } = useFormatDate();

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
    } = props;
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

    return (
        <PortalLayoutGallery>
            <Head title={t('My files')} />

            <div>
                <div className="flex items-start justify-between">
                    <Heading title={folder?.name ?? t('My files')} description={t('The files shared with you')} />
                    <div className="flex items-center gap-2">
                        <PortalFilesToolbarGallery
                            categories={categories}
                            values={values}
                            set={set}
                            setMany={setMany}
                            reset={reset}
                            hasFilters={hasFilters}
                        />
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

                {searching ? (
                    <p className="text-muted-foreground mb-4 text-sm">{t('Showing matches from everything shared with you')}</p>
                ) : (
                    <PortalBreadcrumb breadcrumb={breadcrumb} folderUrl={folderUrl} className="mb-4" />
                )}

                <SelectionBar count={selectionCount} onDownload={downloadSelectionAsZip} onClear={clearSelection} className="mb-4" />

                {folders.length === 0 && files.length === 0 && (
                    <p className="text-muted-foreground rounded-lg border px-4 py-10 text-center text-sm">
                        {searching ? t('No files or folders match your search.') : t('No files have been shared with you yet.')}
                    </p>
                )}

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    {folders.map((row) => (
                        <div
                            key={`folder-${row.id}`}
                            className="relative flex flex-col items-center justify-center gap-2 rounded-xl border p-6 text-center transition hover:border-violet-400 hover:shadow-md"
                        >
                            <Checkbox
                                checked={selectedFolderIds.has(row.id)}
                                onCheckedChange={() => toggleFolder(row.id)}
                                aria-label={t('Select :name', { name: row.name })}
                                className="absolute top-3 left-3"
                            />
                            <Link href={folderUrl(row.id)} className="flex w-full flex-col items-center gap-2">
                                <FolderIcon className="size-10 shrink-0 text-violet-600" strokeWidth={1.5} />
                                <p className="flex w-full items-center justify-center gap-1.5 truncate text-sm font-medium">
                                    <span className="truncate">{row.name}</span>
                                    {row.public && (
                                        <span title={t('Public folder')}>
                                            <Globe className="text-muted-foreground size-3.5 shrink-0" />
                                            <span className="sr-only">{t('Public folder')}</span>
                                        </span>
                                    )}
                                </p>
                            </Link>
                            <span className="absolute top-2 right-2 flex items-center">
                                <FolderRowActions folder={row} onRename={startRename} onDelete={deleteFolder} />
                            </span>
                        </div>
                    ))}

                    {files.map((file) => (
                        <div
                            key={`file-${file.id}`}
                            className="group relative overflow-hidden rounded-xl border transition hover:border-violet-400 hover:shadow-lg"
                        >
                            <Checkbox
                                checked={selectedFileIds.has(file.id)}
                                onCheckedChange={() => toggleFile(file.id)}
                                aria-label={t('Select :name', { name: file.name })}
                                className="bg-background/80 absolute top-3 left-3 z-10"
                            />

                            {/* Over the thumbnail, not beside the name: a card's
                                name line is a few characters wide, and an inline
                                badge truncates it to nothing. */}
                            <VersionBadge version={file.version} variant="gallery" className="absolute top-3 right-3 z-10" />

                            {isThumbnailable(file.mime_type) ? (
                                <FilePreviewDialog
                                    fileId={file.id}
                                    fileName={file.original_name}
                                    className="bg-muted block aspect-square overflow-hidden"
                                >
                                    <img
                                        src={route('files.thumbnail', file.id)}
                                        alt=""
                                        className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    />
                                </FilePreviewDialog>
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
                                    </div>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {formatBytes(file.size)} · {date(file.created_at)}
                                    </p>
                                    <CategoryBadges categories={file.categories} className="mt-1" />
                                </div>
                                {comments_enabled && (
                                    <CommentsShellGallery
                                        fileId={file.id}
                                        defaultOpen={deepLinkedComments === file.id}
                                        fileName={file.name}
                                        count={file.comments_count}
                                        unread={file.unread_comments_count}
                                    />
                                )}
                                <DownloadAction
                                    href={route('files.download', file.id)}
                                    limit={file.download_limit}
                                    variant="ghost"
                                    size="sm"
                                    iconOnly
                                />
                            </div>
                        </div>
                    ))}
                </div>

                <div className="mt-6">
                    <Pagination meta={pagination} />
                </div>
            </div>
            <ZipDownloadDialog status={zip.status} error={zip.error} onClose={zip.close} />
            <RenameFolderDialog folder={renamingFolder} onClose={() => setRenamingFolder(null)} form={renameForm} onSubmit={renameFolder} />
        </PortalLayoutGallery>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Archive, Folder as FolderIcon, Globe, Upload } from 'lucide-react';

import { CommentsShellDrive } from '@/components/comments/shells/comments-shell-drive';
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
import { PortalFilesToolbarDrive } from '@/components/portal/portal-files-toolbar-drive';
import { RenameFolderDialog } from '@/components/portal/rename-folder-dialog';
import { SelectionBar } from '@/components/portal/selection-bar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ZipDownloadDialog } from '@/components/zip-download-dialog';
import { useFolderManagement } from '@/hooks/use-folder-management';
import { useFormatDate } from '@/hooks/use-format-date';
import { usePortalFiles } from '@/hooks/use-portal-files';
import { useTranslation } from '@/hooks/use-translation';
import PortalLayout from '@/layouts/portal-layout';
import { driveFileIcon } from '@/lib/drive-file-icon';
import { formatBytes } from '@/lib/format-bytes';
import { isThumbnailable } from '@/lib/thumbnails';
import { type MyFilesFolderManagementProps } from '@/types/portal';

export default function MyFilesDrive(props: MyFilesFolderManagementProps) {
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
        preview_enabled,
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
        <PortalLayout>
            <Head title={t('My files')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={folder?.name ?? t('My files')} description={t('The files shared with you')} />
                    <div className="flex items-center gap-2">
                        <PortalFilesToolbarDrive
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
                            <Button className="bg-blue-600 hover:bg-blue-700" asChild>
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
                    <p className="mb-3 text-sm text-neutral-500">{t('Showing matches from everything shared with you')}</p>
                ) : (
                    <PortalBreadcrumb
                        breadcrumb={breadcrumb}
                        folderUrl={folderUrl}
                        className="mb-3 text-neutral-500"
                        linkClassName="hover:text-blue-600"
                    />
                )}

                <SelectionBar
                    count={selectionCount}
                    onDownload={downloadSelectionAsZip}
                    onClear={clearSelection}
                    className="mb-3 max-w-3xl border-blue-200 bg-blue-50 px-4 py-2 dark:border-blue-900 dark:bg-blue-950"
                    downloadClassName="bg-blue-600 hover:bg-blue-700"
                />

                <div>
                    {folders.length === 0 && files.length === 0 && (
                        <p className="py-16 text-center text-sm text-neutral-500">
                            {searching ? t('No files or folders match your search.') : t('No files have been shared with you yet.')}
                        </p>
                    )}

                    {(folders.length > 0 || files.length > 0) && (
                        <div className="flex items-center gap-4 border-b border-neutral-200 px-2 pb-2 text-xs font-medium tracking-wide text-neutral-400 uppercase dark:border-neutral-800">
                            <span className="w-5" />
                            <span className="flex-1">{t('Name')}</span>
                            <span className="w-20 text-right">{t('Size')}</span>
                            <span className="w-9" />
                        </div>
                    )}

                    {folders.map((row) => (
                        <div
                            key={`folder-${row.id}`}
                            className="flex items-center gap-4 border-b border-neutral-100 px-2 py-4 hover:bg-blue-50/70 dark:border-neutral-900 dark:hover:bg-blue-950/30"
                        >
                            <Checkbox
                                checked={selectedFolderIds.has(row.id)}
                                onCheckedChange={() => toggleFolder(row.id)}
                                aria-label={t('Select :name', { name: row.name })}
                            />
                            <Link href={folderUrl(row.id)} className="-my-4 flex flex-1 items-center gap-4 py-4">
                                <FolderIcon className="size-6 shrink-0 text-blue-600" />
                                <p className="flex items-center gap-1.5 text-sm font-medium text-neutral-800 dark:text-neutral-200">
                                    {row.name}
                                    {row.public && (
                                        <span title={t('Public folder')}>
                                            <Globe className="size-3.5 shrink-0 text-neutral-400" />
                                            <span className="sr-only">{t('Public folder')}</span>
                                        </span>
                                    )}
                                </p>
                            </Link>
                            <span className="w-20" />
                            <span className="flex items-center justify-end">
                                <FolderRowActions folder={row} onRename={startRename} onDelete={deleteFolder} />
                            </span>
                        </div>
                    ))}

                    {files.map((file) => {
                        const { icon: Icon, color } = driveFileIcon(file.mime_type);

                        return (
                            <div
                                key={`file-${file.id}`}
                                className="flex items-center gap-4 border-b border-neutral-100 px-2 py-4 hover:bg-blue-50/70 dark:border-neutral-900 dark:hover:bg-blue-950/30"
                            >
                                <Checkbox
                                    checked={selectedFileIds.has(file.id)}
                                    onCheckedChange={() => toggleFile(file.id)}
                                    aria-label={t('Select :name', { name: file.name })}
                                />
                                <div className="flex min-w-0 flex-1 items-center gap-4">
                                    <FilePreviewDialog
                                        previewUrl={preview_enabled ? route('files.preview', file.id) : null}
                                        mimeType={file.mime_type}
                                        fileName={file.original_name}
                                        className="shrink-0"
                                    >
                                        {isThumbnailable(file.mime_type) ? (
                                            <img src={route('files.thumbnail', file.id)} alt="" className="size-9 rounded object-cover" />
                                        ) : (
                                            <Icon className={`size-6 shrink-0 ${color}`} strokeWidth={1.5} />
                                        )}
                                    </FilePreviewDialog>
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-1.5">
                                            <p className="truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">{file.name}</p>
                                            {file.public && (
                                                <span title={t('Public file')}>
                                                    <Globe className="size-3.5 shrink-0 text-neutral-400" />
                                                    <span className="sr-only">{t('Public file')}</span>
                                                </span>
                                            )}
                                            <VersionBadge version={file.version} variant="drive" />
                                        </div>
                                        <p className="truncate text-xs text-neutral-500">
                                            {file.description ?? file.original_name} · {date(file.created_at)}
                                        </p>
                                        <CategoryBadges categories={file.categories} className="mt-1" />
                                    </div>
                                </div>
                                <span className="w-20 text-right text-sm text-neutral-500">{formatBytes(file.size)}</span>
                                {/* Fixed-width slots, so the icons form a
                                    column down the list instead of sliding
                                    about with each row's comment count. */}
                                {comments_enabled && (
                                    <div className="flex w-9 shrink-0 justify-center">
                                        <CommentsShellDrive
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
                                    className="w-9"
                                    iconClassName="size-4 text-blue-600"
                                    iconOnly
                                />
                                <DownloadAction
                                    href={route('files.download', file.id)}
                                    limit={file.download_limit}
                                    variant="ghost"
                                    size="sm"
                                    className="w-9"
                                    iconClassName="size-4 text-blue-600"
                                    iconOnly
                                />
                            </div>
                        );
                    })}
                </div>

                <div className="mt-3 max-w-3xl">
                    <Pagination meta={pagination} />
                </div>
            </div>
            <ZipDownloadDialog status={zip.status} error={zip.error} onClose={zip.close} />
            <RenameFolderDialog folder={renamingFolder} onClose={() => setRenamingFolder(null)} form={renameForm} onSubmit={renameFolder} />
        </PortalLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Archive, File as FileIcon, Folder as FolderIcon, Globe, Upload } from 'lucide-react';

import { CommentsShellCompact } from '@/components/comments/shells/comments-shell-compact';
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
import { PortalFilesToolbarCompact } from '@/components/portal/portal-files-toolbar-compact';
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
import { formatBytes } from '@/lib/format-bytes';
import { isThumbnailable } from '@/lib/thumbnails';
import { type MyFilesFolderManagementProps } from '@/types/portal';

export default function MyFilesCompact(props: MyFilesFolderManagementProps) {
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

            <div className="px-4 py-4">
                <div className="mb-3 flex items-start justify-between">
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
                            size="sm"
                        />
                        {can_upload && can_upload_here && (
                            <Button size="sm" asChild>
                                <Link href={route('my-files.upload.create', folder !== null ? { folder: folder.id } : {})}>
                                    <Upload className="size-4" />
                                    {t('Upload a file')}
                                </Link>
                            </Button>
                        )}
                        {folder !== null && !searching && (
                            <Button variant="outline" size="sm" onClick={() => zip.start({ folder_ids: [folder.id] })}>
                                <Archive className="size-4" />
                                {t('Download as zip')}
                            </Button>
                        )}
                    </div>
                </div>

                <PortalFilesToolbarCompact
                    categories={categories}
                    values={values}
                    set={set}
                    setMany={setMany}
                    reset={reset}
                    hasFilters={hasFilters}
                />

                {searching ? (
                    <p className="text-muted-foreground mb-2 text-sm">{t('Showing matches from everything shared with you')}</p>
                ) : (
                    <PortalBreadcrumb breadcrumb={breadcrumb} folderUrl={folderUrl} className="mb-2" />
                )}

                <SelectionBar
                    count={selectionCount}
                    onDownload={downloadSelectionAsZip}
                    onClear={clearSelection}
                    className="mb-2 rounded-md px-3 py-1.5"
                />

                <div className="overflow-x-auto rounded-none border border-neutral-300 dark:border-neutral-700">
                    <table className="w-full border-collapse text-xs">
                        <thead>
                            <tr className="border-b border-neutral-300 bg-neutral-100 text-neutral-500 uppercase dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                                <th className="w-8 px-2 py-1 text-left font-medium"></th>
                                <th className="px-2 py-1 text-left font-medium">{t('Name')}</th>
                                <th className="w-24 px-2 py-1 text-right font-medium">{t('Size')}</th>
                                <th className="w-28 px-2 py-1 text-right font-medium">{t('Modified')}</th>
                                <th className="w-8 px-2 py-1"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                            {folders.length === 0 && files.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-4 py-8 text-center">
                                        {searching ? t('No files or folders match your search.') : t('No files have been shared with you yet.')}
                                    </td>
                                </tr>
                            )}

                            {folders.map((row) => (
                                <tr key={`folder-${row.id}`} className="hover:bg-neutral-100 dark:hover:bg-neutral-900">
                                    <td className="px-2 py-1">
                                        <Checkbox
                                            checked={selectedFolderIds.has(row.id)}
                                            onCheckedChange={() => toggleFolder(row.id)}
                                            aria-label={t('Select :name', { name: row.name })}
                                        />
                                    </td>
                                    <td colSpan={3} className="px-2 py-1">
                                        <Link href={folderUrl(row.id)} className="flex items-center gap-1.5 font-medium hover:underline">
                                            <FolderIcon className="size-3.5 shrink-0 text-neutral-500" />
                                            {row.name}
                                            {row.public && (
                                                <span title={t('Public folder')}>
                                                    <Globe className="size-3 shrink-0 text-neutral-400" />
                                                    <span className="sr-only">{t('Public folder')}</span>
                                                </span>
                                            )}
                                        </Link>
                                    </td>
                                    <td className="px-2 py-1 text-right whitespace-nowrap">
                                        <FolderRowActions folder={row} onRename={startRename} onDelete={deleteFolder} />
                                    </td>
                                </tr>
                            ))}

                            {files.map((file) => (
                                <tr key={`file-${file.id}`} className="hover:bg-neutral-100 dark:hover:bg-neutral-900">
                                    <td className="px-2 py-1 align-top">
                                        <Checkbox
                                            checked={selectedFileIds.has(file.id)}
                                            onCheckedChange={() => toggleFile(file.id)}
                                            aria-label={t('Select :name', { name: file.name })}
                                        />
                                    </td>
                                    <td className="px-2 py-1">
                                        <div className="flex items-start gap-1.5">
                                            <FilePreviewDialog
                                                previewUrl={preview_enabled ? route('files.preview', file.id) : null}
                                                mimeType={file.mime_type}
                                                fileName={file.original_name}
                                                className="shrink-0"
                                            >
                                                {isThumbnailable(file.mime_type) ? (
                                                    <img
                                                        src={route('files.thumbnail', file.id)}
                                                        alt=""
                                                        className="size-5 border border-neutral-300 object-cover dark:border-neutral-700"
                                                    />
                                                ) : (
                                                    <FileIcon className="mt-0.5 size-3.5 shrink-0 text-neutral-400" strokeWidth={1.5} />
                                                )}
                                            </FilePreviewDialog>
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-1.5">
                                                    <p className="truncate font-medium">{file.name}</p>
                                                    {file.public && (
                                                        <span title={t('Public file')}>
                                                            <Globe className="size-3 shrink-0 text-neutral-400" />
                                                            <span className="sr-only">{t('Public file')}</span>
                                                        </span>
                                                    )}
                                                    <VersionBadge version={file.version} variant="compact" />
                                                </div>
                                                {file.description && <p className="truncate text-[11px] text-neutral-400">{file.description}</p>}
                                                <CategoryBadges categories={file.categories} size="xs" className="mt-0.5" />
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-2 py-1 text-right align-top text-neutral-500 tabular-nums">{formatBytes(file.size)}</td>
                                    <td className="px-2 py-1 text-right align-top text-neutral-500 tabular-nums">{date(file.created_at)}</td>
                                    <td className="px-1 py-1 text-right align-top">
                                        <div className="flex items-center justify-end gap-0.5">
                                            {comments_enabled && (
                                                <CommentsShellCompact
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
                                                className="size-6 p-0"
                                                iconClassName="size-3.5"
                                                iconOnly
                                            />
                                            <DownloadAction
                                                href={route('files.download', file.id)}
                                                limit={file.download_limit}
                                                variant="ghost"
                                                size="sm"
                                                className="size-6 p-0"
                                                iconClassName="size-3.5"
                                                iconOnly
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-3">
                    <Pagination meta={pagination} />
                </div>
            </div>
            <ZipDownloadDialog status={zip.status} error={zip.error} onClose={zip.close} />
            <RenameFolderDialog folder={renamingFolder} onClose={() => setRenamingFolder(null)} form={renameForm} onSubmit={renameFolder} />
        </PortalLayout>
    );
}

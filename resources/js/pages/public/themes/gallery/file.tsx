import { Head } from '@inertiajs/react';
import { File as FileIcon } from 'lucide-react';

import { CommentsShellGallery } from '@/components/comments/shells/comments-shell-gallery';
import { DownloadAction } from '@/components/download-action';
import { PreviewAction } from '@/components/preview-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { type VersionLinks } from '@/components/files/version-badge';
import { VersionNotice } from '@/components/files/version-notice';
import { formatBytes } from '@/lib/format-bytes';
import { FilePreviewDialog } from '@/components/file-preview-dialog';
import { isThumbnailable } from '@/lib/thumbnails';
import { type DownloadLimit } from '@/types/portal';
import PublicLayoutGallery from '../../../../layouts/public-layout-gallery';

interface PublicFileShowProps {
    file: {
        name: string;
        description: string | null;
        original_name: string;
        size: number;
        mime_type: string;
        /** Already narrowed to counterparts that are themselves public and unexpired. */
        version: VersionLinks;
        categories: CategoryTag[];
    };
    thumbnail_url: string | null;
    /**
     * Null unless this visitor is actually offered a preview — the
     * server has already weighed the setting, the file's type and its
     * download limit, so a theme never re-derives any of them.
     */
    preview_url: string | null;
    download_url: string;
    download_limit: DownloadLimit;
    comments_enabled: boolean;
    comments_endpoint: string;
}

export default function PublicFileShowGallery({
    file,
    thumbnail_url,
    preview_url,
    download_url,
    download_limit,
    comments_enabled,
    comments_endpoint,
}: PublicFileShowProps) {

    return (
        <PublicLayoutGallery title={file.name} description={`${file.original_name} · ${formatBytes(file.size)}`}>
            <Head title={file.name} />

            <div className="mx-auto flex max-w-2xl flex-col items-center gap-6">
                <div className="bg-muted flex w-full items-center justify-center overflow-hidden rounded-xl border shadow-lg">
                    <FilePreviewDialog
                        previewUrl={preview_url}
                        mimeType={file.mime_type}
                        fileName={file.original_name}
                        className="flex w-full items-center justify-center"
                    >
                        {thumbnail_url && isThumbnailable(file.mime_type) ? (
                            <img src={thumbnail_url} alt="" className="max-h-[32rem] w-full object-contain" />
                        ) : (
                            <FileIcon className="text-muted-foreground m-24 size-20" strokeWidth={1.25} />
                        )}
                    </FilePreviewDialog>
                </div>

                {file.description && <p className="text-muted-foreground text-center text-sm">{file.description}</p>}

                <CategoryBadges categories={file.categories} className="justify-center" />

                <VersionNotice version={file.version} className="w-full" />

                <div className="flex items-center gap-2">
                    <PreviewAction
                        previewUrl={preview_url}
                        mimeType={file.mime_type}
                        fileName={file.original_name}
                        variant="outline"
                        size="lg"
                    />
                    <DownloadAction href={download_url} limit={download_limit} size="lg" />
                </div>

                {comments_enabled && (
                    <div className="w-full">
                        <CommentsShellGallery fileName={file.name} endpoint={comments_endpoint} inline />
                    </div>
                )}
            </div>
        </PublicLayoutGallery>
    );
}

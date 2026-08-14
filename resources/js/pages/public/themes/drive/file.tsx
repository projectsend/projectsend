import { Head } from '@inertiajs/react';

import { CommentsShellDrive } from '@/components/comments/shells/comments-shell-drive';
import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { type VersionLinks } from '@/components/files/version-badge';
import { VersionNotice } from '@/components/files/version-notice';
import PublicLayoutDrive from '@/layouts/public-layout-drive';
import { driveFileIcon } from '@/lib/drive-file-icon';
import { formatBytes } from '@/lib/format-bytes';
import { isThumbnailable } from '@/lib/thumbnails';
import { type DownloadLimit } from '@/types/portal';

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
    download_url: string;
    download_limit: DownloadLimit;
    comments_enabled: boolean;
    comments_endpoint: string;
}

export default function PublicFileShowDrive({
    file,
    thumbnail_url,
    download_url,
    download_limit,
    comments_enabled,
    comments_endpoint,
}: PublicFileShowProps) {
    const { icon: Icon, color } = driveFileIcon(file.mime_type);

    return (
        <PublicLayoutDrive title={file.name} description={`${file.original_name} · ${formatBytes(file.size)}`}>
            <Head title={file.name} />

            <div className="mx-auto flex max-w-lg flex-col items-center gap-6">
                <div className="flex w-full items-center justify-center overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900">
                    {thumbnail_url && isThumbnailable(file.mime_type) ? (
                        <img src={thumbnail_url} alt="" className="max-h-80 w-full object-contain" />
                    ) : (
                        <Icon className={`m-16 size-16 ${color}`} strokeWidth={1.5} />
                    )}
                </div>

                {file.description && <p className="text-center text-sm text-neutral-500">{file.description}</p>}

                <CategoryBadges categories={file.categories} className="justify-center" />

                <VersionNotice version={file.version} className="w-full" />

                <DownloadAction
                    href={download_url}
                    limit={download_limit}
                    className="bg-blue-600 hover:bg-blue-700"
                    variant="default"
                    size="default"
                />

                {comments_enabled && (
                    <div className="w-full">
                        <CommentsShellDrive fileName={file.name} endpoint={comments_endpoint} inline />
                    </div>
                )}
            </div>
        </PublicLayoutDrive>
    );
}

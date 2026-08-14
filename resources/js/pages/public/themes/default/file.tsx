import { Head } from '@inertiajs/react';

import { CommentsShellDefault } from '@/components/comments/shells/comments-shell-default';
import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { type VersionLinks } from '@/components/files/version-badge';
import { VersionNotice } from '@/components/files/version-notice';
import PublicLayout from '@/layouts/public-layout';
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

export default function PublicFileShow({
    file,
    thumbnail_url,
    download_url,
    download_limit,
    comments_enabled,
    comments_endpoint,
}: PublicFileShowProps) {

    return (
        <PublicLayout title={file.name} description={`${file.original_name} · ${formatBytes(file.size)}`}>
            <Head title={file.name} />

            <div className="flex flex-col items-center gap-6">
                {thumbnail_url && isThumbnailable(file.mime_type) && (
                    <img src={thumbnail_url} alt="" className="max-h-80 max-w-full rounded-lg border object-contain" />
                )}

                {file.description && <p className="text-muted-foreground text-center text-sm">{file.description}</p>}

                <CategoryBadges categories={file.categories} className="justify-center" />

                <VersionNotice version={file.version} className="w-full" />

                <DownloadAction href={download_url} limit={download_limit} size="default" />

                {comments_enabled && (
                    <div className="w-full max-w-lg">
                        <CommentsShellDefault fileName={file.name} endpoint={comments_endpoint} inline />
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}

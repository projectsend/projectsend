import { Head } from '@inertiajs/react';

import { CommentsShellCompact } from '@/components/comments/shells/comments-shell-compact';
import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { type VersionLinks } from '@/components/files/version-badge';
import { VersionNotice } from '@/components/files/version-notice';
import PublicLayoutCompact from '@/layouts/public-layout-compact';
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

export default function PublicFileShowCompact({
    file,
    thumbnail_url,
    download_url,
    download_limit,
    comments_enabled,
    comments_endpoint,
}: PublicFileShowProps) {

    return (
        <PublicLayoutCompact title={file.name} description={`${file.original_name} · ${formatBytes(file.size)}`}>
            <Head title={file.name} />

            <div className="flex items-start gap-4 rounded-md border p-4">
                {thumbnail_url && isThumbnailable(file.mime_type) && (
                    <img src={thumbnail_url} alt="" className="size-24 shrink-0 rounded border object-cover" />
                )}

                <div className="min-w-0 flex-1 space-y-2">
                    {file.description && <p className="text-muted-foreground text-sm">{file.description}</p>}

                    <CategoryBadges categories={file.categories} size="xs" />

                    <VersionNotice version={file.version} className="w-full" />

                    <DownloadAction href={download_url} limit={download_limit} size="sm" />
                </div>
            </div>

            {comments_enabled && (
                <div className="mt-3">
                    <CommentsShellCompact fileName={file.name} endpoint={comments_endpoint} inline />
                </div>
            )}
        </PublicLayoutCompact>
    );
}

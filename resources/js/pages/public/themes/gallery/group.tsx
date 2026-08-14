import { Head, Link } from '@inertiajs/react';
import { File as FileIcon } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { VersionBadge, type VersionLinks } from '@/components/files/version-badge';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/format-bytes';
import { type DownloadLimit } from '@/types/portal';
import PublicLayoutGallery from '../../../../layouts/public-layout-gallery';

interface PublicFile {
    name: string;
    /** Already narrowed to counterparts that are themselves public and unexpired. */
    version: VersionLinks;
    size: number;
    url: string;
    download_url: string;
    download_limit: DownloadLimit;
    thumbnail_url: string | null;
    categories: CategoryTag[];
}

interface PublicGroupShowProps {
    group: {
        name: string;
        description: string | null;
    };
    files: PublicFile[];
    pagination: PaginationMeta;
}

export default function PublicGroupShowGallery({ group, files, pagination }: PublicGroupShowProps) {
    const { t } = useTranslation();

    return (
        <PublicLayoutGallery title={group.name} description={group.description ?? undefined}>
            <Head title={group.name} />

            {files.length === 0 ? (
                <p className="text-muted-foreground text-center text-sm">{t('No files here yet.')}</p>
            ) : (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    {files.map((file) => (
                        <div key={file.url} className="group overflow-hidden rounded-xl border transition hover:border-violet-400 hover:shadow-lg">
                            <Link href={file.url} className="bg-muted block aspect-square overflow-hidden">
                                {file.thumbnail_url ? (
                                    <img
                                        src={file.thumbnail_url}
                                        alt=""
                                        className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    />
                                ) : (
                                    <div className="flex h-full items-center justify-center">
                                        <FileIcon className="text-muted-foreground size-10" strokeWidth={1.25} />
                                    </div>
                                )}
                            </Link>
                            <div className="flex items-center justify-between gap-2 p-3">
                                <Link href={file.url} className="min-w-0 hover:underline">
                                    <p className="truncate text-sm font-medium">{file.name}</p>
                                    <p className="text-muted-foreground text-xs">{formatBytes(file.size)}</p>
                                    <CategoryBadges categories={file.categories} className="mt-1" />
                                </Link>
                                <VersionBadge version={file.version} variant="gallery" className="shrink-0" />
                                <DownloadAction href={file.download_url} limit={file.download_limit} variant="ghost" size="sm" iconOnly />
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Pagination meta={pagination} />
        </PublicLayoutGallery>
    );
}

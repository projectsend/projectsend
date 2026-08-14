import { Head, Link } from '@inertiajs/react';
import { Info } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { VersionBadge, type VersionLinks } from '@/components/files/version-badge';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayout from '@/layouts/public-layout';
import { formatBytes } from '@/lib/format-bytes';
import { type DownloadLimit } from '@/types/portal';

interface PublicFile {
    name: string;
    /** Already narrowed to counterparts that are themselves public and unexpired. */
    version: VersionLinks;
    size: number;
    url: string;
    download_url: string;
    download_limit: DownloadLimit;
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

export default function PublicGroupShow({ group, files, pagination }: PublicGroupShowProps) {
    const { t } = useTranslation();

    return (
        <PublicLayout title={group.name} description={group.description ?? undefined}>
            <Head title={group.name} />

            <div className="rounded-lg border">
                {files.length === 0 && <p className="text-muted-foreground px-4 py-6 text-center text-sm">{t('No files here yet.')}</p>}
                {files.map((file) => (
                    <div key={file.url} className="flex items-center justify-between gap-2 border-b px-4 py-3 last:border-0">
                        <Link href={file.url} className="min-w-0 hover:underline">
                            <p className="flex items-center gap-1.5 truncate text-sm font-medium">
                                {file.name}
                                <VersionBadge version={file.version} />
                            </p>
                            <p className="text-muted-foreground text-xs">{formatBytes(file.size)}</p>
                            <CategoryBadges categories={file.categories} className="mt-1" />
                        </Link>
                        <div className="flex shrink-0 gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={file.url}>
                                    <Info className="size-4" />
                                    {t('Details')}
                                </Link>
                            </Button>
                            <DownloadAction href={file.download_url} limit={file.download_limit} variant="outline" size="sm" />
                        </div>
                    </div>
                ))}
            </div>

            <Pagination meta={pagination} />
        </PublicLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Info } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { PublicListControls } from '@/components/public-list-controls';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { type PublicListOrder, type PublicZip, usePublicListing } from '@/hooks/use-public-listing';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayout from '@/layouts/public-layout';
import { formatBytes } from '@/lib/format-bytes';
import { type DownloadLimit } from '@/types/portal';

interface PublicFile {
    slug: string;
    name: string;
    size: number;
    url: string;
    download_url: string;
    download_limit: DownloadLimit;
    categories: CategoryTag[];
}

interface PublicFolderShowProps extends PublicListOrder {
    folder: {
        name: string;
    };
    files: PublicFile[];
    pagination: PaginationMeta;
    zip: PublicZip | null;
}

export default function PublicFolderShow({ folder, files, pagination, sort, direction, zip }: PublicFolderShowProps) {
    const { t } = useTranslation();
    const listing = usePublicListing({ sort, direction, files, zip });
    const { canZip, selection } = listing;

    return (
        <PublicLayout title={folder.name}>
            <Head title={folder.name} />

            <PublicListControls listing={listing} total={pagination.total} rows={files.length} />

            <div className="rounded-lg border">
                {files.length === 0 && <p className="text-muted-foreground px-4 py-6 text-center text-sm">{t('No files here yet.')}</p>}
                {files.map((file) => (
                    <div key={file.url} className="flex items-center justify-between gap-2 border-b px-4 py-3 last:border-0">
                        {canZip && (
                            <Checkbox
                                {...selection.checkboxProps({ kind: 'file', id: file.slug })}
                                aria-label={t('Select :name', { name: file.name })}
                            />
                        )}
                        <Link href={file.url} className="mr-auto min-w-0 hover:underline">
                            <p className="truncate text-sm font-medium" title={file.name}>
                                {file.name}
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

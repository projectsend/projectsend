import { Head, Link } from '@inertiajs/react';
import { Info } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { PublicListControls } from '@/components/public-list-controls';
import { SortableHeader } from '@/components/sortable-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { type PublicListOrder, type PublicZip, usePublicListing } from '@/hooks/use-public-listing';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayoutCompact from '@/layouts/public-layout-compact';
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

export default function PublicFolderShowCompact({ folder, files, pagination, sort, direction, zip }: PublicFolderShowProps) {
    const { t } = useTranslation();
    const listing = usePublicListing({ sort, direction, files, zip });
    const { sortState, sortBy, canZip, selection } = listing;

    return (
        <PublicLayoutCompact title={folder.name}>
            <Head title={folder.name} />

            <PublicListControls listing={listing} total={pagination.total} rows={files.length} selectAll={false} />

            <div className="overflow-hidden rounded-none border border-neutral-300 dark:border-neutral-700">
                <table className="w-full border-collapse text-xs">
                    <thead>
                        <tr className="border-b border-neutral-300 bg-neutral-100 text-neutral-500 uppercase dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                            {canZip && (
                                <th className="w-8 px-2 py-1 text-left font-medium">
                                    {files.length > 0 && (
                                        <Checkbox
                                            checked={selection.pageState}
                                            onCheckedChange={(checked) => selection.togglePage(checked === true)}
                                            aria-label={t('Select all on this page')}
                                        />
                                    )}
                                </th>
                            )}
                            <SortableHeader column="name" {...sortState} onSort={sortBy} className="px-2 py-1 text-left uppercase">
                                {t('Name')}
                            </SortableHeader>
                            <SortableHeader
                                column="size"
                                {...sortState}
                                onSort={sortBy}
                                firstDirection="desc"
                                className="w-24 px-2 py-1 text-right uppercase"
                            >
                                {t('Size')}
                            </SortableHeader>
                            <th className="w-20 px-2 py-1"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                        {files.length === 0 && (
                            <tr>
                                <td colSpan={canZip ? 4 : 3} className="text-muted-foreground px-4 py-8 text-center">
                                    {t('No files here yet.')}
                                </td>
                            </tr>
                        )}
                        {files.map((file) => (
                            <tr key={file.url} className="hover:bg-neutral-100 dark:hover:bg-neutral-900">
                                {canZip && (
                                    <td className="px-2 py-1 align-top">
                                        <Checkbox
                                            {...selection.checkboxProps({ kind: 'file', id: file.slug })}
                                            aria-label={t('Select :name', { name: file.name })}
                                        />
                                    </td>
                                )}
                                <td className="px-2 py-1">
                                    <Link href={file.url} className="font-medium hover:underline">
                                        {file.name}
                                    </Link>
                                    <CategoryBadges categories={file.categories} size="xs" className="mt-0.5" />
                                </td>
                                <td className="px-2 py-1 text-right text-neutral-500 tabular-nums">{formatBytes(file.size)}</td>
                                <td className="px-1 py-1 text-right">
                                    <div className="flex justify-end gap-0.5">
                                        <Button variant="ghost" size="sm" className="size-6 p-0" asChild>
                                            <Link href={file.url}>
                                                <Info className="size-3.5" />
                                                <span className="sr-only">{t('Details')}</span>
                                            </Link>
                                        </Button>
                                        <DownloadAction
                                            href={file.download_url}
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

            <Pagination meta={pagination} />
        </PublicLayoutCompact>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Info } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayoutCompact from '@/layouts/public-layout-compact';
import { formatBytes } from '@/lib/format-bytes';
import { type DownloadLimit } from '@/types/portal';

interface PublicFile {
    name: string;
    size: number;
    url: string;
    download_url: string;
    download_limit: DownloadLimit;
    categories: CategoryTag[];
}

interface PublicFolderShowProps {
    folder: {
        name: string;
    };
    files: PublicFile[];
    pagination: PaginationMeta;
}

export default function PublicFolderShowCompact({ folder, files, pagination }: PublicFolderShowProps) {
    const { t } = useTranslation();

    return (
        <PublicLayoutCompact title={folder.name}>
            <Head title={folder.name} />

            <div className="overflow-hidden rounded-none border border-neutral-300 dark:border-neutral-700">
                <table className="w-full border-collapse text-xs">
                    <thead>
                        <tr className="border-b border-neutral-300 bg-neutral-100 text-neutral-500 uppercase dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                            <th className="px-2 py-1 text-left font-medium">{t('Name')}</th>
                            <th className="w-24 px-2 py-1 text-right font-medium">{t('Size')}</th>
                            <th className="w-20 px-2 py-1"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                        {files.length === 0 && (
                            <tr>
                                <td colSpan={3} className="text-muted-foreground px-4 py-8 text-center">
                                    {t('No files here yet.')}
                                </td>
                            </tr>
                        )}
                        {files.map((file) => (
                            <tr key={file.url} className="hover:bg-neutral-100 dark:hover:bg-neutral-900">
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

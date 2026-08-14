import { Head, Link } from '@inertiajs/react';
import { Folder, Info } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayoutCompact from '@/layouts/public-layout-compact';
import { formatBytes } from '@/lib/format-bytes';
import { type DownloadLimit } from '@/types/portal';

interface PublicGroup {
    name: string;
    description: string | null;
    url: string;
}

interface PublicFolder {
    name: string;
    url: string;
}

interface PublicFile {
    name: string;
    size: number;
    url: string;
    download_url: string;
    download_limit: DownloadLimit;
    categories: CategoryTag[];
}

interface PublicIndexProps {
    groups: PublicGroup[];
    folders: PublicFolder[];
    files: PublicFile[];
    pagination: PaginationMeta;
}

export default function PublicIndexCompact({ groups, folders, files, pagination }: PublicIndexProps) {
    const { t } = useTranslation();

    return (
        <PublicLayoutCompact title={t('Public files')} description={t('Browse publicly shared groups and files below.')}>
            <Head title={t('Public files')} />

            <div className="overflow-hidden rounded-none border border-neutral-300 dark:border-neutral-700">
                <table className="w-full border-collapse text-xs">
                    <thead>
                        <tr className="border-b border-neutral-300 bg-neutral-100 text-neutral-500 uppercase dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                            <th className="w-8 px-2 py-1 text-left font-medium"></th>
                            <th className="px-2 py-1 text-left font-medium">{t('Name')}</th>
                            <th className="w-24 px-2 py-1 text-right font-medium">{t('Size')}</th>
                            <th className="w-20 px-2 py-1"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                        {groups.map((group) => (
                            <tr key={group.url} className="hover:bg-neutral-100 dark:hover:bg-neutral-900">
                                <td className="px-2 py-1">
                                    <Folder className="size-3.5 text-neutral-500" />
                                </td>
                                <td className="px-2 py-1">
                                    <Link href={group.url} className="font-medium hover:underline">
                                        {group.name}
                                    </Link>
                                    {group.description && <span className="ml-2 text-[11px] text-neutral-400">{group.description}</span>}
                                </td>
                                <td className="px-2 py-1 text-right text-neutral-400">—</td>
                                <td className="px-2 py-1 text-right">
                                    <Link href={group.url} className="text-neutral-400 hover:underline">
                                        {t('Group')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {folders.map((folder) => (
                            <tr key={folder.url} className="hover:bg-neutral-100 dark:hover:bg-neutral-900">
                                <td className="px-2 py-1">
                                    <Folder className="size-3.5 text-neutral-500" />
                                </td>
                                <td className="px-2 py-1">
                                    <Link href={folder.url} className="font-medium hover:underline">
                                        {folder.name}
                                    </Link>
                                </td>
                                <td className="px-2 py-1 text-right text-neutral-400">—</td>
                                <td className="px-2 py-1 text-right">
                                    <Link href={folder.url} className="text-neutral-400 hover:underline">
                                        {t('Folder')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {files.map((file) => (
                            <tr key={file.url} className="hover:bg-neutral-100 dark:hover:bg-neutral-900">
                                <td className="px-2 py-1" />
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
                        {groups.length === 0 && folders.length === 0 && files.length === 0 && (
                            <tr>
                                <td colSpan={4} className="text-muted-foreground px-4 py-8 text-center">
                                    {t('Nothing is publicly shared yet.')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination meta={pagination} />
        </PublicLayoutCompact>
    );
}

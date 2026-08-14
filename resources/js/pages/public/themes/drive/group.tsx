import { Head, Link } from '@inertiajs/react';
import { Info } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { VersionBadge, type VersionLinks } from '@/components/files/version-badge';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayoutDrive from '@/layouts/public-layout-drive';
import { driveFileIcon } from '@/lib/drive-file-icon';
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
    thumbnail_url: string | null;
    mime_type: string;
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

export default function PublicGroupShowDrive({ group, files, pagination }: PublicGroupShowProps) {
    const { t } = useTranslation();

    return (
        <PublicLayoutDrive title={group.name} description={group.description ?? undefined}>
            <Head title={group.name} />

            <div>
                {files.length > 0 && (
                    <div className="flex items-center gap-4 border-b border-neutral-200 px-2 pb-2 text-xs font-medium tracking-wide text-neutral-400 uppercase dark:border-neutral-800">
                        <span className="size-6" />
                        <span className="flex-1">{t('Name')}</span>
                        <span className="w-20 text-right">{t('Size')}</span>
                        <span className="w-20" />
                    </div>
                )}
                {files.length === 0 && <p className="py-16 text-center text-sm text-neutral-500">{t('No files here yet.')}</p>}
                {files.map((file) => {
                    const { icon: Icon, color } = driveFileIcon(file.mime_type);

                    return (
                        <div
                            key={file.url}
                            className="flex items-center gap-4 border-b border-neutral-100 px-2 py-4 hover:bg-blue-50/70 dark:border-neutral-900 dark:hover:bg-blue-950/30"
                        >
                            <Link href={file.url} className="flex min-w-0 flex-1 items-center gap-4">
                                <Icon className={`size-6 shrink-0 ${color}`} strokeWidth={1.5} />
                                <div className="min-w-0">
                                    <p className="flex items-center gap-2 truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">
                                        {file.name}
                                        <VersionBadge version={file.version} variant="drive" />
                                    </p>
                                    <CategoryBadges categories={file.categories} className="mt-1" />
                                </div>
                            </Link>
                            <span className="w-20 text-right text-sm text-neutral-500">{formatBytes(file.size)}</span>
                            <div className="flex w-20 shrink-0 justify-end gap-0.5">
                                <Button variant="ghost" size="sm" className="size-8 p-0" asChild>
                                    <Link href={file.url}>
                                        <Info className="size-4" />
                                        <span className="sr-only">{t('Details')}</span>
                                    </Link>
                                </Button>
                                <DownloadAction
                                    href={file.download_url}
                                    limit={file.download_limit}
                                    variant="ghost"
                                    size="sm"
                                    className="size-8 p-0"
                                    iconClassName="size-4 text-blue-600"
                                    iconOnly
                                />
                            </div>
                        </div>
                    );
                })}
            </div>

            <Pagination meta={pagination} />
        </PublicLayoutDrive>
    );
}

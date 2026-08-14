import { Head, Link } from '@inertiajs/react';
import { Folder } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayoutDrive from '@/layouts/public-layout-drive';
import { driveFileIcon } from '@/lib/drive-file-icon';
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
    thumbnail_url: string | null;
    mime_type: string;
    categories: CategoryTag[];
}

interface PublicIndexProps {
    groups: PublicGroup[];
    folders: PublicFolder[];
    files: PublicFile[];
    pagination: PaginationMeta;
}

export default function PublicIndexDrive({ groups, folders, files, pagination }: PublicIndexProps) {
    const { t } = useTranslation();

    return (
        <PublicLayoutDrive title={t('Public files')} description={t('Browse publicly shared groups and files below.')}>
            <Head title={t('Public files')} />

            <div>
                {(groups.length > 0 || folders.length > 0 || files.length > 0) && (
                    <div className="flex items-center gap-4 border-b border-neutral-200 px-2 pb-2 text-xs font-medium tracking-wide text-neutral-400 uppercase dark:border-neutral-800">
                        <span className="size-6" />
                        <span className="flex-1">{t('Name')}</span>
                        <span className="w-20 text-right">{t('Size')}</span>
                        <span className="w-9" />
                    </div>
                )}

                {groups.map((group) => (
                    <Link
                        key={group.url}
                        href={group.url}
                        className="flex items-center gap-4 border-b border-neutral-100 px-2 py-4 hover:bg-blue-50/70 dark:border-neutral-900 dark:hover:bg-blue-950/30"
                    >
                        <Folder className="size-6 shrink-0 text-blue-600" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">{group.name}</p>
                            {group.description && <p className="truncate text-xs text-neutral-500">{group.description}</p>}
                        </div>
                        <span className="w-20" />
                        <span className="w-9" />
                    </Link>
                ))}

                {folders.map((folder) => (
                    <Link
                        key={folder.url}
                        href={folder.url}
                        className="flex items-center gap-4 border-b border-neutral-100 px-2 py-4 hover:bg-blue-50/70 dark:border-neutral-900 dark:hover:bg-blue-950/30"
                    >
                        <Folder className="size-6 shrink-0 text-blue-600" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">{folder.name}</p>
                        </div>
                        <span className="w-20" />
                        <span className="w-9" />
                    </Link>
                ))}

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
                                    <p className="truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">{file.name}</p>
                                    <CategoryBadges categories={file.categories} className="mt-1" />
                                </div>
                            </Link>
                            <span className="w-20 text-right text-sm text-neutral-500">{formatBytes(file.size)}</span>
                            <DownloadAction
                                href={file.download_url}
                                limit={file.download_limit}
                                variant="ghost"
                                size="sm"
                                className="w-9"
                                iconClassName="size-4 text-blue-600"
                                iconOnly
                            />
                        </div>
                    );
                })}

                {groups.length === 0 && folders.length === 0 && files.length === 0 && (
                    <p className="py-16 text-center text-sm text-neutral-500">{t('Nothing is publicly shared yet.')}</p>
                )}

                <Pagination meta={pagination} />
            </div>
        </PublicLayoutDrive>
    );
}

import { Head, Link } from '@inertiajs/react';
import { File as FileIcon, Folder } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/format-bytes';
import { type DownloadLimit } from '@/types/portal';
import PublicLayoutGallery from '../../../../layouts/public-layout-gallery';

interface PublicGroup {
    name: string;
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
    categories: CategoryTag[];
}

interface PublicIndexProps {
    groups: PublicGroup[];
    folders: PublicFolder[];
    files: PublicFile[];
    pagination: PaginationMeta;
}

export default function PublicIndexGallery({ groups, folders, files, pagination }: PublicIndexProps) {
    const { t } = useTranslation();

    return (
        <PublicLayoutGallery title={t('Public files')} description={t('Browse publicly shared groups and files below.')}>
            <Head title={t('Public files')} />

            <div className="space-y-10">
                {groups.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-muted-foreground text-sm font-medium">{t('Groups')}</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            {groups.map((group) => (
                                <Link
                                    key={group.url}
                                    href={group.url}
                                    className="flex flex-col items-center gap-2 rounded-xl border p-6 text-center transition hover:border-violet-400 hover:shadow-md"
                                >
                                    <Folder className="size-10 shrink-0 text-violet-600" strokeWidth={1.5} />
                                    <p className="w-full truncate text-sm font-medium">{group.name}</p>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {folders.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-muted-foreground text-sm font-medium">{t('Folders')}</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            {folders.map((folder) => (
                                <Link
                                    key={folder.url}
                                    href={folder.url}
                                    className="flex flex-col items-center gap-2 rounded-xl border p-6 text-center transition hover:border-violet-400 hover:shadow-md"
                                >
                                    <Folder className="size-10 shrink-0 text-violet-600" strokeWidth={1.5} />
                                    <p className="w-full truncate text-sm font-medium">{folder.name}</p>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {files.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-muted-foreground text-sm font-medium">{t('Files')}</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            {files.map((file) => (
                                <div
                                    key={file.url}
                                    className="group overflow-hidden rounded-xl border transition hover:border-violet-400 hover:shadow-lg"
                                >
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
                                        <DownloadAction href={file.download_url} limit={file.download_limit} variant="ghost" size="sm" iconOnly />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {groups.length === 0 && folders.length === 0 && files.length === 0 && (
                    <p className="text-muted-foreground text-center text-sm">{t('Nothing is publicly shared yet.')}</p>
                )}

                <Pagination meta={pagination} />
            </div>
        </PublicLayoutGallery>
    );
}

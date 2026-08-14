import { Head, Link } from '@inertiajs/react';
import { Folder, Info } from 'lucide-react';

import { DownloadAction } from '@/components/download-action';
import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import PublicLayout from '@/layouts/public-layout';
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

export default function PublicIndex({ groups, folders, files, pagination }: PublicIndexProps) {
    const { t } = useTranslation();

    return (
        <PublicLayout title={t('Public files')} description={t('Browse publicly shared groups and files below.')}>
            <Head title={t('Public files')} />

            <div className="space-y-8">
                {groups.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-muted-foreground text-sm font-medium">{t('Groups')}</h2>
                        <div className="rounded-lg border">
                            {groups.map((group) => (
                                <Link
                                    key={group.url}
                                    href={group.url}
                                    className="hover:bg-accent flex items-center gap-3 border-b px-4 py-3 last:border-0"
                                >
                                    <Folder className="text-muted-foreground size-5 shrink-0" />
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">{group.name}</p>
                                        {group.description && <p className="text-muted-foreground truncate text-xs">{group.description}</p>}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {folders.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-muted-foreground text-sm font-medium">{t('Folders')}</h2>
                        <div className="rounded-lg border">
                            {folders.map((folder) => (
                                <Link
                                    key={folder.url}
                                    href={folder.url}
                                    className="hover:bg-accent flex items-center gap-3 border-b px-4 py-3 last:border-0"
                                >
                                    <Folder className="text-muted-foreground size-5 shrink-0" />
                                    <p className="truncate text-sm font-medium">{folder.name}</p>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {files.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-muted-foreground text-sm font-medium">{t('Files')}</h2>
                        <div className="rounded-lg border">
                            {files.map((file) => (
                                <div key={file.url} className="flex items-center justify-between gap-2 border-b px-4 py-3 last:border-0">
                                    <Link href={file.url} className="min-w-0 hover:underline">
                                        <p className="truncate text-sm font-medium">{file.name}</p>
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
                    </div>
                )}

                {groups.length === 0 && folders.length === 0 && files.length === 0 && (
                    <p className="text-muted-foreground text-center text-sm">{t('Nothing is publicly shared yet.')}</p>
                )}

                <Pagination meta={pagination} />
            </div>
        </PublicLayout>
    );
}

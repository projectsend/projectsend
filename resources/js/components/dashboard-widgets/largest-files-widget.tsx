import { Link } from '@inertiajs/react';
import { Download } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/format-bytes';

export interface LargestFile {
    id: number;
    name: string;
    size: number;
    uploader_name: string | null;
    created_at: string;
    edit_url: string | null;
    download_url: string | null;
    uploader_edit_url: string | null;
}

export function LargestFilesWidget({ largestFiles }: { largestFiles: LargestFile[] }) {
    const { t } = useTranslation();

    return (
        <div>
            <div className="space-y-2">
                {largestFiles.map((file) => (
                    <div key={file.id} className="flex items-baseline justify-between gap-4 text-sm">
                        <p className="min-w-0 truncate">
                            {file.edit_url ? (
                                <Link href={file.edit_url} className="font-medium hover:underline">
                                    {file.name}
                                </Link>
                            ) : (
                                <span className="font-medium">{file.name}</span>
                            )}{' '}
                            <span className="text-muted-foreground">
                                {t('by')}{' '}
                                {file.uploader_name ? (
                                    file.uploader_edit_url ? (
                                        <Link href={file.uploader_edit_url} className="hover:text-foreground hover:underline">
                                            {file.uploader_name}
                                        </Link>
                                    ) : (
                                        file.uploader_name
                                    )
                                ) : (
                                    t('(deleted account)')
                                )}
                            </span>
                        </p>
                        <span className="flex shrink-0 items-center gap-2">
                            <span className="text-xs font-semibold">{formatBytes(file.size)}</span>
                            {file.download_url && (
                                <a
                                    href={file.download_url}
                                    className="text-muted-foreground hover:text-foreground"
                                    aria-label={t('Download :name', { name: file.name })}
                                >
                                    <Download className="size-3.5" />
                                </a>
                            )}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

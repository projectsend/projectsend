import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

import Heading from '@/components/heading';
import ChunkedUploadDashboard from '@/components/uploads/chunked-upload-dashboard';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface FilesCreateProps {
    /** The folder the upload page was opened from, which uploads go into. */
    folder: { id: number; name: string } | null;
    max_file_size_mb: number;
    part_size_mb: number;
    allowed_extensions: string[] | null;
}

export default function FilesCreate({ folder, max_file_size_mb, part_size_mb, allowed_extensions }: FilesCreateProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('All files'), href: '/files' },
        { title: t('Upload'), href: '/files/upload' },
    ];

    const handleComplete = (fileIds: number[]) => {
        if (fileIds.length === 1) {
            router.visit(route('files.edit', fileIds[0]));
        } else if (fileIds.length > 1) {
            // Back to where the upload started, so the new files are in view.
            router.visit(route('files.index', folder !== null ? { folder: folder.id } : {}));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Upload')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Upload files')}
                    description={
                        max_file_size_mb > 0
                            ? t('Up to :max MB per file. Uploads can be paused and resume automatically after interruptions.', {
                                  max: max_file_size_mb,
                              })
                            : t('No size limit. Uploads can be paused and resume automatically after interruptions.')
                    }
                />

                {folder !== null && (
                    <p className="text-muted-foreground mb-4 text-sm">{t('Uploading into :folder', { folder: folder.name })}</p>
                )}

                <ChunkedUploadDashboard
                    folderId={folder?.id}
                    maxFileSizeMb={max_file_size_mb}
                    partSizeMb={part_size_mb}
                    allowedExtensions={allowed_extensions}
                    onComplete={handleComplete}
                />
            </div>
        </AppLayout>
    );
}

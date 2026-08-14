import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { NewVersionPicker } from '@/components/files/new-version-picker';
import Heading from '@/components/heading';
import ChunkedUploadDashboard from '@/components/uploads/chunked-upload-dashboard';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import PortalLayout from '@/layouts/portal-layout';
import { formatBytes } from '@/lib/format-bytes';

interface PortalUploadProps {
    allowed_extensions: string[] | null;
    max_file_size_mb: number;
    part_size_mb: number;
    remaining_bytes: number | null;
    quota_bytes: number | null;
    used_bytes: number;
    theme: string;
    folder: { id: number; name: string } | null;
    version_candidates_url: string;
}

export default function PortalUpload({
    allowed_extensions,
    max_file_size_mb,
    part_size_mb,
    remaining_bytes,
    quota_bytes,
    used_bytes,
    theme,
    folder,
    version_candidates_url,
}: PortalUploadProps) {
    const { t } = useTranslation();
    const [previousFileId, setPreviousFileId] = useState<number | null>(null);
    const [versionError, setVersionError] = useState<string | null>(null);

    const usagePercent = quota_bytes !== null && quota_bytes > 0 ? Math.min(100, Math.round((used_bytes / quota_bytes) * 100)) : 0;

    const content = (
        <>
            <Head title={t('Upload a file')} />

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

                {folder !== null && <p className="text-muted-foreground mb-4 text-sm">{t('Uploading into ":name".', { name: folder.name })}</p>}

                {quota_bytes !== null && (
                    <div className="border-primary/20 bg-primary/5 mb-6 rounded-lg border p-4">
                        <p className="text-sm">
                            <span className="font-semibold">{formatBytes(used_bytes)}</span>
                            <span className="text-muted-foreground"> {t('of')} </span>
                            <span className="font-semibold">{formatBytes(quota_bytes)}</span>
                            <span className="text-muted-foreground"> {t('used')}</span>
                        </p>
                        <div className="bg-muted mt-2 h-2.5 w-full overflow-hidden rounded-full">
                            <div
                                className={`h-full rounded-full transition-all ${usagePercent >= 90 ? 'bg-destructive' : 'bg-primary'}`}
                                style={{ width: `${usagePercent}%` }}
                            />
                        </div>
                    </div>
                )}

                <NewVersionPicker
                    candidatesUrl={version_candidates_url}
                    value={previousFileId}
                    onChange={setPreviousFileId}
                    warnAboutInheritedSharing
                />

                {versionError !== null && <p className="text-destructive mb-4 text-sm">{versionError}</p>}

                <ChunkedUploadDashboard
                    maxFileSizeMb={max_file_size_mb}
                    partSizeMb={part_size_mb}
                    allowedExtensions={allowed_extensions}
                    remainingBytes={remaining_bytes}
                    folderId={folder?.id}
                    previousFileId={previousFileId}
                    onVersionError={setVersionError}
                    onComplete={() => {
                        // Stay put when the link failed, so the message is
                        // actually read — the file itself did upload.
                        if (versionError !== null) return;
                        router.visit(route('my-files.index', folder !== null ? { folder: folder.id } : {}));
                    }}
                />
            </div>
        </>
    );

    // The "default" theme uses the app's own sidebar shell for /my-files
    // (see portal/themes/default/my-files.tsx) rather than the dedicated
    // PortalLayout header every other portal theme uses — this page is
    // shared across all themes (no portal/themes/{key}/upload.tsx copies),
    // so it has to pick the matching shell itself instead of always using
    // one or the other.
    if (theme === 'default') {
        const breadcrumbs: BreadcrumbItem[] = [
            { title: t('My files'), href: '/my-files' },
            { title: t('Upload a file'), href: '/my-files/upload' },
        ];

        return <AppLayout breadcrumbs={breadcrumbs}>{content}</AppLayout>;
    }

    return <PortalLayout>{content}</PortalLayout>;
}

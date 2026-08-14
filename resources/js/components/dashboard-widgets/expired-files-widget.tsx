import { Link } from '@inertiajs/react';

import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

export interface ExpiredFile {
    id: number;
    name: string;
    expires_at: string | null;
    edit_url: string | null;
}

export interface ExpiredFilesSummary {
    count: number;
    files: ExpiredFile[];
    auto_delete_enabled: boolean;
    next_run_at: string;
}

export function ExpiredFilesWidget({ expiredFiles }: { expiredFiles: ExpiredFilesSummary }) {
    const { t } = useTranslation();
    // The cleanup run is a moment (it matters what time it fires); a
    // file's expiry was chosen as a plain date and is stored as the end
    // of it, so the 23:59 is an artefact rather than something anybody
    // picked.
    const { date, dateTime } = useFormatDate();

    return (
        <div className="space-y-3">
            {expiredFiles.auto_delete_enabled && (
                <p className="text-muted-foreground text-xs">{t('Next cleanup :date', { date: dateTime(expiredFiles.next_run_at) })}</p>
            )}

            {expiredFiles.count === 0 ? (
                <p className="text-muted-foreground text-sm">{t('No expired files.')}</p>
            ) : (
                <div className="space-y-2">
                    {expiredFiles.files.map((file) => (
                        <div key={file.id} className="flex items-baseline justify-between gap-4 text-sm">
                            <p className="min-w-0 truncate">
                                {file.edit_url ? (
                                    <Link href={file.edit_url} className="font-medium hover:underline">
                                        {file.name}
                                    </Link>
                                ) : (
                                    <span className="font-medium">{file.name}</span>
                                )}
                            </p>
                            {file.expires_at && (
                                <span className="text-muted-foreground shrink-0 text-xs">{t('Expired :date', { date: date(file.expires_at) })}</span>
                            )}
                        </div>
                    ))}
                    {expiredFiles.count > expiredFiles.files.length && (
                        <p className="text-muted-foreground text-xs">
                            {t('and :count more', { count: String(expiredFiles.count - expiredFiles.files.length) })}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

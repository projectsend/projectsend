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
    /** True when the viewer's role limits them to their own clients, in which case this lists only their own uploads. */
    scoped: boolean;
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

            {/* A warning about what is due to be deleted has to say what it
                covers. A limited role sees only its own uploads here, and
                would otherwise read an empty list as "nothing to worry
                about" on behalf of files it cannot see. */}
            {expiredFiles.scoped && <p className="text-muted-foreground text-xs">{t('Files you uploaded. Your clients’ files are not listed here.')}</p>}

            {expiredFiles.count === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {expiredFiles.scoped ? t('None of your uploads have expired.') : t('No expired files.')}
                </p>
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

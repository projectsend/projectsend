import { usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useTranslation } from '@/hooks/use-translation';
import { type SharedData } from '@/types';

/**
 * Says out loud that this server is not running the code it was updated
 * to — the one update failure that has no other symptom.
 *
 * With OPcache configured the way production guides recommend, replacing
 * the files and reloading nothing leaves the database on the new version
 * and every visitor on the old code, indefinitely and without an error
 * anywhere. `projectsend:update` records what it applied; this compares it
 * with what the process rendering this page actually compiled.
 *
 * Deliberately on every staff page rather than the dashboard alone: the
 * person who missed the reload has no reason to suspect anything, so the
 * notice has to find them rather than wait to be visited.
 */
export function CodeNoticeBanner() {
    const { t } = useTranslation();
    const { code_notice: notice } = usePage<SharedData>().props;

    if (!notice) {
        return null;
    }

    const container = notice.install_kind === 'container';

    // Stated as a fact first, cause second. A deliberate rollback lands
    // here too, and telling somebody to reload PHP-FPM when they meant to
    // go back a version would send them the wrong way.
    const title =
        notice.reason === 'stale_code'
            ? t('This server is running version :running, but version :applied is installed', {
                  running: notice.running,
                  applied: notice.applied,
              })
            : t('Version :running is installed, but the update has not been run', { running: notice.running });

    return (
        <Alert variant={notice.reason === 'stale_code' ? 'destructive' : 'warning'}>
            <AlertTriangle className="size-4" />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription className="space-y-2">
                {notice.reason === 'stale_code' ? (
                    <>
                        <p>
                            {container
                                ? t('The database was updated to :applied, and this container is still running the code it started with.', {
                                      applied: notice.applied,
                                  })
                                : t(
                                      'The database was updated to :applied, and PHP is still serving the code it had compiled before that. Reloading it clears this notice.',
                                      { applied: notice.applied },
                                  )}
                        </p>
                        <Command>{container ? 'docker compose up -d --force-recreate' : 'sudo systemctl reload php8.4-fpm'}</Command>
                        {!container && (
                            <p className="text-xs">
                                {t('If you rolled back on purpose, run php artisan projectsend:update so the database matches the code you restored.')}
                            </p>
                        )}
                    </>
                ) : (
                    <>
                        <p>
                            {t(
                                'New files are in place, but the database has only been brought up to :applied. Until the update runs, this installation is running code its database does not match.',
                                { applied: notice.applied },
                            )}
                        </p>
                        <Command>{container ? 'docker compose up -d --force-recreate' : 'sudo ./update.sh'}</Command>
                    </>
                )}
            </AlertDescription>
        </Alert>
    );
}

function Command({ children }: { children: string }) {
    return <code className="bg-background/60 inline-block rounded px-2 py-1 font-mono text-xs">{children}</code>;
}

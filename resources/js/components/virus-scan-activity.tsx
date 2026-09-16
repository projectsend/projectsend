import { router } from '@inertiajs/react';
import { CheckCircle2, Loader2, ShieldAlert, ShieldQuestion } from 'lucide-react';
import { useEffect, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

/** Often enough to feel live, rarely enough to be a read every few seconds. */
const POLL_INTERVAL_MS = 4000;

interface ScannedFile {
    id: number;
    name: string;
    status: string;
    note: string | null;
    scanned_at: string | null;
    engine: string | null;
}

interface Activity {
    running: boolean;
    /** New uploads withheld until they are checked. */
    waiting: number;
    /** Jobs on the scans queue — a backfill lives here, not in `waiting`. */
    queued: number;
    checked_last_hour: number;
    last_scanned_at: string | null;
    never_scanned: number;
    quarantined: number;
    recent: ScannedFile[];
}

/**
 * What the scanner is doing, refreshed while somebody is watching.
 *
 * A backfill runs for minutes or hours in a queue worker, where nothing
 * about it is visible: this is the only place it can be watched. When
 * nothing is running the same list is the record of what was decided
 * last, which is what a person opening this tab after the fact is
 * looking for.
 */
export function VirusScanActivity() {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const [activity, setActivity] = useState<Activity | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let stopped = false;

        const poll = () => {
            fetch(route('system-settings.virus-scanning.activity'), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((body: Activity) => {
                    if (!stopped) {
                        setActivity(body);
                        setFailed(false);
                    }
                })
                .catch(() => {
                    // A missed poll tries again on the next tick. Only say
                    // so once nothing has ever arrived, or a blip would
                    // replace a screen full of real numbers with an error.
                    if (!stopped) setFailed(true);
                });
        };

        poll();
        const intervalId = window.setInterval(poll, POLL_INTERVAL_MS);
        // A scan started from the Options tab lands here as a redirect
        // back; refresh then rather than waiting out the interval.
        const stopOnSuccess = router.on('success', poll);

        return () => {
            stopped = true;
            window.clearInterval(intervalId);
            stopOnSuccess();
        };
    }, []);

    if (activity === null) {
        return (
            <p className="text-muted-foreground text-sm">
                {failed ? t('Could not read what the scanner is doing.') : t('Loading…')}
            </p>
        );
    }

    const badge = (file: ScannedFile) => {
        if (file.status === 'clean') {
            return (
                <Badge variant="secondary" className="gap-1 font-normal">
                    <CheckCircle2 className="size-3" /> {t('Clean')}
                </Badge>
            );
        }

        if (file.status === 'infected' || file.status === 'unscannable_blocked') {
            return (
                <Badge variant="destructive" className="gap-1 font-normal">
                    <ShieldAlert className="size-3" /> {file.note ?? t('Quarantined')}
                </Badge>
            );
        }

        if (file.status === 'released') {
            return (
                <Badge variant="secondary" className="font-normal">
                    {t('Released')}
                </Badge>
            );
        }

        return (
            <Badge variant="outline" className="gap-1 font-normal">
                <ShieldQuestion className="size-3" /> {file.note ?? t('Not scanned')}
            </Badge>
        );
    };

    return (
        <div className="space-y-6">
            <div className="rounded-lg border p-4">
                <div className="flex items-center gap-2">
                    {activity.running && <Loader2 className="text-muted-foreground size-4 animate-spin" />}
                    <HeadingSmall
                        title={activity.running ? t('Scanning now') : t('Nothing is being scanned')}
                        description={
                            activity.running
                                ? t(':count files still to check.', { count: Math.max(activity.waiting, activity.queued) })
                                : activity.last_scanned_at
                                  ? t('Last checked :when.', { when: dateTime(activity.last_scanned_at) })
                                  : t('Nothing has been checked yet.')
                        }
                    />
                </div>

                <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-5">
                    <div>
                        {/* Two different facts, and the difference matters:
                            an upload nobody can download yet, and work the
                            scanner has not reached. A backfill shows up in
                            the second and never in the first. */}
                        <dt className="text-muted-foreground">{t('Uploads held')}</dt>
                        <dd className="font-medium">{activity.waiting}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">{t('In the queue')}</dt>
                        <dd className="font-medium">{activity.queued}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">{t('Checked in the last hour')}</dt>
                        <dd className="font-medium">{activity.checked_last_hour}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">{t('In quarantine')}</dt>
                        <dd className="font-medium">{activity.quarantined}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">{t('Never scanned')}</dt>
                        <dd className="font-medium">{activity.never_scanned}</dd>
                    </div>
                </dl>
            </div>

            <TableShell
                columns={[t('File'), t('Result'), t('Checked')]}
                isEmpty={activity.recent.length === 0}
                emptyMessage={<>{t('No file has been checked yet.')}</>}
            >
                {activity.recent.map((file) => (
                    <tr key={file.id} className="border-b last:border-0">
                        <td className="px-4 py-2.5 font-medium">{file.name}</td>
                        <td className="px-4 py-2.5">{badge(file)}</td>
                        <td className="text-muted-foreground px-4 py-2.5">{dateTime(file.scanned_at)}</td>
                    </tr>
                ))}
            </TableShell>

            {failed && <p className="text-muted-foreground text-xs">{t('The last refresh did not go through. Still trying.')}</p>}
        </div>
    );
}

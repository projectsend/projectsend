import { usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import { type SharedData } from '@/types';

/**
 * Says out loud that nothing is building zip downloads.
 *
 * Zip building runs on its own queue so that one large archive cannot
 * hold up every notification email behind it. The cost of that is a
 * worker which has to be told about the new queue — and a manual install
 * whose command still reads a plain `queue:work` keeps sending email
 * perfectly while no zip ever finishes, with nothing in any log to say
 * so. The person who missed the change has no reason to suspect
 * anything, which is why this goes looking for them rather than waiting
 * on a settings page to be visited.
 *
 * The fix, not the symptom: somebody reading "downloads are not being
 * processed" still has to work out what to do about it.
 */
export function WorkerNoticeBanner() {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const { worker_notice: notice } = usePage<SharedData>().props;

    if (!notice) {
        return null;
    }

    return (
        <Alert variant="warning" className="mb-4">
            <AlertTriangle className="size-4" />
            <AlertTitle>{t('Nothing is building zip downloads')}</AlertTitle>
            <AlertDescription>
                {t(
                    'A zip download has been waiting since :date and no background worker has picked it up. Zip downloads run on their own queue now, so a worker started before this version watches the wrong one — everything else, including email, keeps working.',
                    { date: dateTime(notice.waiting_since) },
                )}{' '}
                {t('Your worker command needs --queue=default,zips. INSTALL.md has the full service file.')}
            </AlertDescription>
        </Alert>
    );
}

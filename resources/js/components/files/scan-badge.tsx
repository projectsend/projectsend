import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

export interface ScanState {
    status: 'pending' | 'clean' | 'infected' | 'released' | 'not_scanned' | 'unscannable_blocked' | 'missing';
    /** The threat name, or why it was not scanned. Already translated. */
    note: string | null;
}

/**
 * What the virus scanner made of this file, for a staff member's list.
 *
 * Nothing at all for a clean file, which is the common case: a badge on
 * every row would say nothing and cost the eye something. The four that
 * do show are the ones somebody may have to act on.
 *
 * Recipients never see this — a file they should not have simply is not
 * there. This is a staff-side affordance only.
 */
export function ScanBadge({ scan }: { scan: ScanState | null | undefined }) {
    const { t } = useTranslation();

    if (!scan || scan.status === 'clean') return null;

    if (scan.status === 'pending') {
        return (
            <Badge variant="outline" className="text-[11px] font-normal" title={t('Nobody can download it until this finishes.')}>
                {t('Checking')}
            </Badge>
        );
    }

    if (scan.status === 'infected' || scan.status === 'unscannable_blocked') {
        return (
            <Badge variant="destructive" className="text-[11px] font-normal" title={scan.note ?? undefined}>
                {t('Quarantined')}
            </Badge>
        );
    }

    if (scan.status === 'missing') {
        return (
            <Badge variant="destructive" className="text-[11px] font-normal" title={t('The file is no longer in storage.')}>
                {t('Missing')}
            </Badge>
        );
    }

    if (scan.status === 'released') {
        return (
            <Badge variant="secondary" className="text-[11px] font-normal" title={t('An administrator released this file from quarantine.')}>
                {t('Released')}
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="text-[11px] font-normal" title={scan.note ?? undefined}>
            {t('Not scanned')}
        </Badge>
    );
}

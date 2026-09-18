import { ShieldQuestion } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

interface UnscannedNoticeProps {
    /** Whether this file went out without being checked. Decided by the server. */
    unscanned?: boolean;
    className?: string;
}

/**
 * Told to whoever opens a public link: this file was never checked.
 *
 * It happens on an installation that scans and chose to let files through
 * anyway — too large for the scanner, an archive it could not open, or an
 * upload that arrived while the scanner was down. The uploader and the
 * staff library both see that state on the file; the person following the
 * link neither chose the policy nor can see the setting, and until this
 * they were the only one with no signal at all.
 *
 * Stated plainly and without alarm: nothing is known to be wrong with the
 * file. What is known is that nothing looked.
 */
export function UnscannedNotice({ unscanned, className }: UnscannedNoticeProps) {
    const { t } = useTranslation();

    if (!unscanned) {
        return null;
    }

    return (
        <p className={cn('text-muted-foreground flex items-center justify-center gap-1.5 text-xs', className)}>
            <ShieldQuestion className="size-3.5 shrink-0" />
            {t('This file was not checked for viruses.')}
        </p>
    );
}

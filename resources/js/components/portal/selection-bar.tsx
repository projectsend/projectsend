import { Archive, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

interface SelectionBarProps {
    count: number;
    onDownload: () => void;
    onClear: () => void;
    /** Theme spacing, border and background for the bar. */
    className?: string;
    /** Theme accent for the download button (Drive tints it blue). */
    downloadClassName?: string;
}

/**
 * The "3 selected — Download as zip / Clear" bar that appears once anything
 * is ticked.
 *
 * Renders nothing when the selection is empty, so callers need no guard of
 * their own.
 */
export function SelectionBar({ count, onDownload, onClear, className, downloadClassName }: SelectionBarProps) {
    const { t } = useTranslation();

    if (count === 0) {
        return null;
    }

    return (
        <div className={cn('bg-muted/40 flex items-center justify-between gap-3 rounded-lg border px-4 py-2', className)}>
            <p className="text-sm font-medium">{t(':count selected', { count })}</p>
            <div className="flex items-center gap-2">
                <Button size="sm" className={downloadClassName} onClick={onDownload}>
                    <Archive className="size-4" />
                    {t('Download as zip')}
                </Button>
                <Button variant="ghost" size="sm" onClick={onClear}>
                    <X className="size-4" />
                    {t('Clear')}
                </Button>
            </div>
        </div>
    );
}

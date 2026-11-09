import { Checkbox } from '@/components/ui/checkbox';
import { type Selection } from '@/hooks/use-selection';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * "Select all on this page", for layouts with no table header to hold the
 * checkbox: grids of cards. Ticks every row on screen, and shows a dash
 * when only some of them are ticked.
 */
export function SelectPageCheckbox({ selection, className }: { selection: Selection; className?: string }) {
    const { t } = useTranslation();

    return (
        <label className={cn('text-muted-foreground flex items-center gap-2 text-sm', className)}>
            <Checkbox checked={selection.pageState} onCheckedChange={(checked) => selection.togglePage(checked === true)} />
            {t('Select all on this page')}
        </label>
    );
}

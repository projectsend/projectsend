import { type SortDirection } from '@/components/sortable-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type PublicListOrder } from '@/hooks/use-public-listing';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { SORT_OPTIONS } from '@/types/portal';

/**
 * The "Sort by" select on public listing pages: the same six orders the
 * client portal offers. Shown in every theme, including the ones whose
 * column headers also sort, because a date has no column to click.
 */
export function PublicSortSelect({
    sortState,
    sortBy,
    className,
}: {
    sortState: PublicListOrder;
    sortBy: (column: string, direction: SortDirection) => void;
    className?: string;
}) {
    const { t } = useTranslation();

    return (
        <div className={cn('flex items-center justify-end gap-2', className)}>
            <span className="text-muted-foreground text-sm">{t('Sort by')}</span>
            <Select
                value={`${sortState.sort}-${sortState.direction}`}
                onValueChange={(value) => {
                    const [column, direction] = value.split('-');
                    sortBy(column, direction as SortDirection);
                }}
            >
                <SelectTrigger className="h-8 w-auto min-w-[9rem]" aria-label={t('Sort by')}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {SORT_OPTIONS.map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                            {t(label)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

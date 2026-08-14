import { LayoutGrid, List } from 'lucide-react';

import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { type ViewMode } from '@/hooks/use-view-mode';

/** List/grid switcher for a file listing — see useViewMode for persistence. */
export function ViewModeToggle({ value, onChange }: { value: ViewMode; onChange: (mode: ViewMode) => void }) {
    const { t } = useTranslation();

    return (
        <ToggleGroup type="single" value={value} onValueChange={(next) => next && onChange(next as ViewMode)} className="rounded-md border p-0.5">
            <ToggleGroupItem value="list" size="sm" aria-label={t('List view')}>
                <List className="size-4" />
            </ToggleGroupItem>
            <ToggleGroupItem value="grid" size="sm" aria-label={t('Grid view')}>
                <LayoutGrid className="size-4" />
            </ToggleGroupItem>
        </ToggleGroup>
    );
}

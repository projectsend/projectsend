import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

import { PortalFilesFilterFields } from '@/components/portal/portal-files-toolbar';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useTranslation } from '@/hooks/use-translation';
import { type CategoryTag } from '@/types/portal';

interface PortalFilesToolbarCompactProps {
    categories: CategoryTag[];
    values: Record<string, string>;
    set: (key: string, value: string, debounce?: boolean) => void;
    setMany: (updates: Record<string, string>, debounce?: boolean) => void;
    reset: () => void;
    hasFilters: boolean;
}

/**
 * The compact theme's toolbar: a full-width collapsible row matching the
 * dense spreadsheet look, instead of a permanently open box.
 */
export function PortalFilesToolbarCompact({ categories, values, set, setMany, reset, hasFilters }: PortalFilesToolbarCompactProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="mb-3 border border-neutral-300 dark:border-neutral-700">
            <CollapsibleTrigger className="flex w-full items-center gap-1.5 bg-neutral-100 px-2 py-1 text-left text-xs font-medium tracking-wide text-neutral-500 uppercase hover:bg-neutral-200 dark:bg-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800">
                <ChevronRight className={`size-3.5 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`} />
                {t('Filters')}
                {hasFilters && <span className="size-1.5 shrink-0 rounded-full bg-neutral-500 dark:bg-neutral-400" />}
            </CollapsibleTrigger>
            <CollapsibleContent className="flex flex-wrap items-end gap-3 border-t border-neutral-300 p-2 dark:border-neutral-700">
                <PortalFilesFilterFields categories={categories} values={values} set={set} setMany={setMany} />
                {hasFilters && (
                    <Button type="button" variant="ghost" size="sm" onClick={reset}>
                        {t('Clear')}
                    </Button>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}

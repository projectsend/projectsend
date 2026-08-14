import { Search } from 'lucide-react';

import { PortalFilesFilterFields } from '@/components/portal/portal-files-toolbar';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/use-translation';
import { type CategoryTag } from '@/types/portal';

interface PortalFilesToolbarDriveProps {
    categories: CategoryTag[];
    values: Record<string, string>;
    set: (key: string, value: string, debounce?: boolean) => void;
    setMany: (updates: Record<string, string>, debounce?: boolean) => void;
    reset: () => void;
    hasFilters: boolean;
}

/**
 * The drive theme's toolbar: a search icon that opens a right-side sheet
 * with all filters, matching Drive's own slide-in panel convention.
 */
export function PortalFilesToolbarDrive({ categories, values, set, setMany, reset, hasFilters }: PortalFilesToolbarDriveProps) {
    const { t } = useTranslation();

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" size="icon" className="relative border-neutral-300 text-blue-600 dark:border-neutral-700">
                    <Search className="size-4" />
                    <span className="sr-only">{t('Search and filter')}</span>
                    {hasFilters && <span className="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-blue-600" />}
                </Button>
            </SheetTrigger>
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle>{t('Search and filter')}</SheetTitle>
                </SheetHeader>
                <div className="mt-4 flex flex-col gap-4">
                    <PortalFilesFilterFields categories={categories} values={values} set={set} setMany={setMany} fullWidth />
                    {hasFilters && (
                        <Button type="button" variant="ghost" className="justify-start text-blue-600 hover:text-blue-700" onClick={reset}>
                            {t('Clear')}
                        </Button>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

import { Search } from 'lucide-react';

import { PortalFilesFilterFields } from '@/components/portal/portal-files-toolbar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { type CategoryTag } from '@/types/portal';

interface PortalFilesToolbarGalleryProps {
    categories: CategoryTag[];
    values: Record<string, string>;
    set: (key: string, value: string, debounce?: boolean) => void;
    setMany: (updates: Record<string, string>, debounce?: boolean) => void;
    reset: () => void;
    hasFilters: boolean;
}

/**
 * The gallery theme's toolbar: a search icon that opens a centered modal
 * with all filters, like a lightbox control panel over the thumbnail grid.
 */
export function PortalFilesToolbarGallery({ categories, values, set, setMany, reset, hasFilters }: PortalFilesToolbarGalleryProps) {
    const { t } = useTranslation();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="icon" className="relative rounded-xl text-violet-600">
                    <Search className="size-4" />
                    <span className="sr-only">{t('Search and filter')}</span>
                    {hasFilters && <span className="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-violet-600" />}
                </Button>
            </DialogTrigger>
            <DialogContent className="rounded-xl sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Search and filter')}</DialogTitle>
                </DialogHeader>
                <div className="grid grid-cols-2 gap-4">
                    <PortalFilesFilterFields categories={categories} values={values} set={set} setMany={setMany} fullWidth />
                </div>
                {hasFilters && (
                    <Button
                        type="button"
                        variant="ghost"
                        className="justify-start text-violet-600 hover:text-violet-700"
                        onClick={reset}
                    >
                        {t('Clear')}
                    </Button>
                )}
            </DialogContent>
        </Dialog>
    );
}

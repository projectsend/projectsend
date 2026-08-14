import { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The filter/search bar above a list table. Fields flow in a wrapping row; a
 * "Clear" button appears at the end once any filter is active.
 */
export function ListToolbar({ children, showClear, onClear }: { children: ReactNode; showClear: boolean; onClear: () => void }) {
    const { t } = useTranslation();

    return (
        <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border p-4">
            {children}
            {showClear && (
                <Button type="button" variant="ghost" onClick={onClear}>
                    {t('Clear')}
                </Button>
            )}
        </div>
    );
}

/** A labelled cell inside <ListToolbar>. */
export function FilterField({ label, htmlFor, children }: { label: string; htmlFor?: string; children: ReactNode }) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={htmlFor} className="text-xs">
                {label}
            </Label>
            {children}
        </div>
    );
}

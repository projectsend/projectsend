import { Lock } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

export interface UploadStorageLocation {
    id: number;
    name: string;
    note: string | null;
}

/**
 * "Store in": which of the client's own storage locations an upload goes
 * to, the preferred one first and preselected. Locked while a batch is
 * uploading, so every file in it lands in the same place.
 */
export function StorageLocationPicker({
    locations,
    value,
    onChange,
    locked,
}: {
    locations: UploadStorageLocation[];
    value: number;
    onChange: (id: number) => void;
    locked: boolean;
}) {
    const { t } = useTranslation();
    const chosen = locations.find((location) => location.id === value);

    return (
        <fieldset className="mb-6" disabled={locked}>
            <legend className="mb-2 text-sm font-medium">{t('Store in')}</legend>
            <div className="grid gap-2 sm:grid-cols-2">
                {locations.map((location, index) => (
                    <label
                        key={location.id}
                        className={`relative flex items-start gap-3 rounded-lg border p-3 transition-colors ${
                            locked ? 'cursor-not-allowed opacity-70' : 'hover:bg-muted/40 cursor-pointer'
                        } has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:checked]:ring-primary has-[:checked]:ring-1 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2`}
                    >
                        <input
                            type="radio"
                            name="storage_location"
                            value={location.id}
                            checked={location.id === value}
                            onChange={() => onChange(location.id)}
                            className="accent-primary mt-0.5 size-4 shrink-0"
                        />
                        <span className="min-w-0">
                            <span className="flex flex-wrap items-center gap-1.5 text-sm font-medium">
                                {location.name}
                                {index === 0 && <Badge variant="secondary">{t('Default')}</Badge>}
                            </span>
                            {location.note && <span className="text-muted-foreground block text-xs">{location.note}</span>}
                        </span>
                    </label>
                ))}
            </div>
            <p className="text-muted-foreground mt-2 flex items-center gap-1.5 text-xs" aria-live="polite">
                {locked && <Lock className="size-3" />}
                {locked
                    ? t('Uploading to :name. You can choose again once this upload finishes.', { name: chosen?.name ?? '' })
                    : t('Files you add go to :name.', { name: chosen?.name ?? '' })}
            </p>
        </fieldset>
    );
}

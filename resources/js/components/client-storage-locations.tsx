import { ChevronDown, ChevronUp, Cylinder, HardDrive, Plus, X } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

export interface StorageLocationOption {
    id: number;
    name: string;
    local: boolean;
    location: string;
    accepts_uploads: boolean;
    is_default: boolean;
}

export interface ClientStorageProps {
    can_assign: boolean;
    options: StorageLocationOption[];
    assigned_ids: number[];
    files_count: number;
}

/**
 * The client edit page's "Storage locations" card: the places this
 * client's uploads may go, the first preferred. Reordered with the arrows
 * (no drag, so it works the same by keyboard and on a phone). Read-only
 * for an editor who may not change the installation's settings.
 */
export function ClientStorageLocations({
    storage,
    value,
    onChange,
    error,
}: {
    storage: ClientStorageProps;
    value: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}) {
    const { t } = useTranslation();
    const byId = new Map(storage.options.map((option) => [option.id, option]));
    const assigned = value.map((id) => byId.get(id)).filter((option): option is StorageLocationOption => option !== undefined);
    const available = storage.options.filter((option) => !value.includes(option.id));
    const [adding, setAdding] = useState('');
    const defaultLocation = storage.options.find((option) => option.is_default);

    const move = (index: number, by: number) => {
        const next = [...value];
        const [moved] = next.splice(index, 1);
        next.splice(index + by, 0, moved);
        onChange(next);
    };

    const add = () => {
        const id = Number(adding);
        if (id > 0) onChange([...value, id]);
        setAdding('');
    };

    return (
        <section className="grid min-w-0 gap-3 rounded-lg border p-4" aria-labelledby="client-storage-locations">
            <div>
                <Label id="client-storage-locations" className="text-base">
                    {t('Storage locations')}
                </Label>
                <p className="text-muted-foreground text-sm">
                    {t('Their uploads go to the first location unless they choose another on the upload screen.')}
                </p>
            </div>

            {assigned.length === 0 ? (
                <p className="text-muted-foreground rounded-md border border-dashed px-3 py-4 text-center text-sm">
                    {t('No locations. Their uploads use the default, :name.', { name: defaultLocation?.name ?? '' })}
                </p>
            ) : (
                <ol className="grid min-w-0 gap-2">
                    {assigned.map((option, index) => (
                        <li
                            key={option.id}
                            className={`flex min-w-0 items-center gap-3 rounded-md border px-3 py-2 ${index === 0 ? 'border-primary/40 bg-primary/5' : ''}`}
                        >
                            <span
                                className={`flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${index === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}
                                aria-label={t('Priority :number', { number: index + 1 })}
                            >
                                {index + 1}
                            </span>
                            <span className="text-muted-foreground hidden sm:block">
                                {option.local ? <HardDrive className="size-4" /> : <Cylinder className="size-4" />}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center gap-1.5 font-medium">
                                    {option.name}
                                    {index === 0 && (
                                        <Badge variant="secondary" className="hidden sm:inline-flex">
                                            {t('Preselected')}
                                        </Badge>
                                    )}
                                    {!option.accepts_uploads && <Badge variant="outline">{t('Existing files only')}</Badge>}
                                </span>
                                <span className="text-muted-foreground block truncate font-mono text-[11px]">{option.location}</span>
                            </span>
                            {storage.can_assign && (
                                <span className="flex shrink-0 gap-0.5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className={`size-8 ${index === 0 ? 'invisible' : ''}`}
                                        onClick={() => move(index, -1)}
                                        aria-label={t('Move :name up', { name: option.name })}
                                    >
                                        <ChevronUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className={`size-8 ${index === assigned.length - 1 ? 'invisible' : ''}`}
                                        onClick={() => move(index, 1)}
                                        aria-label={t('Move :name down', { name: option.name })}
                                    >
                                        <ChevronDown className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-destructive hover:text-destructive size-8"
                                        onClick={() => onChange(value.filter((id) => id !== option.id))}
                                        aria-label={t('Remove :name', { name: option.name })}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </span>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {storage.can_assign && available.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    <Select value={adding} onValueChange={setAdding}>
                        <SelectTrigger className="w-full sm:w-72" aria-label={t('Location to add')}>
                            <SelectValue placeholder={t('Choose a location')} />
                        </SelectTrigger>
                        <SelectContent>
                            {available.map((option) => (
                                <SelectItem key={option.id} value={String(option.id)}>
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" onClick={add} disabled={adding === ''}>
                        <Plus className="size-4" />
                        {t('Add')}
                    </Button>
                </div>
            )}

            {!storage.can_assign && (
                <p className="text-muted-foreground text-xs">{t('Only someone who can change the installation’s settings can change these.')}</p>
            )}

            {error && <p className="text-destructive text-sm">{error}</p>}

            <p className="bg-muted text-muted-foreground rounded-md px-3 py-2 text-xs">
                {t('Changing this affects new uploads only. Files already uploaded (:count) stay where they are.', { count: storage.files_count })}
            </p>
        </section>
    );
}

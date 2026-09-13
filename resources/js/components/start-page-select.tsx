import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

export interface StartPageOption {
    value: string;
    /** Already translated by the server. */
    label: string;
    /** The permission a role needs for this page — role screens only. */
    permission?: string | null;
}

// Radix Select cannot hold an empty value, so "no choice of my own" needs
// a stand-in. Converted back to null on the way out.
const INHERIT = '__inherit';

/**
 * Where somebody lands after signing in. Used on the role screens (the
 * default for everyone in the role) and on the profile (a person's own
 * choice). See StartPages on the server for how the two combine.
 */
export function StartPageSelect({
    value,
    onChange,
    options,
    error,
    description,
    inheritLabel,
    grantedPermissions,
}: {
    value: string | null;
    onChange: (value: string | null) => void;
    options: StartPageOption[];
    error?: string;
    description: string;
    /** When set, offers "no choice of my own" under this label; otherwise null shows as the dashboard. */
    inheritLabel?: string;
    /** On a role screen: the permissions being saved, so pages the role could not open are disabled. */
    grantedPermissions?: string[];
}) {
    const { t } = useTranslation();

    const selected = value ?? (inheritLabel ? INHERIT : 'dashboard');

    return (
        <div className="grid gap-2">
            <Label htmlFor="start_page">{t('Start page')}</Label>
            <Select value={selected} onValueChange={(v) => onChange(v === INHERIT ? null : v)}>
                <SelectTrigger id="start_page" className="w-64 max-w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {inheritLabel && <SelectItem value={INHERIT}>{inheritLabel}</SelectItem>}
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={option.value}
                            disabled={grantedPermissions !== undefined && !!option.permission && !grantedPermissions.includes(option.permission)}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <p className="text-muted-foreground text-xs">{description}</p>
            <InputError message={error} />
        </div>
    );
}

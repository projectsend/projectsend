import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export interface AbilityGroup {
    category: string;
    label: string;
    abilities: { key: string; label: string }[];
}

export interface TokenFormValues {
    name: string;
    abilities: string[];
    expires_in_days: number;
    never_expires: boolean;
}

interface Props {
    values: TokenFormValues;
    setValue: <K extends keyof TokenFormValues>(key: K, value: TokenFormValues[K]) => void;
    errors: Partial<Record<keyof TokenFormValues | 'abilities.0', string>>;
    availableAbilities: AbilityGroup[];
    maxDays: number;
}

/**
 * The fields shared by minting a token and editing one. Kept in one place
 * because the two screens must agree on what may be granted — a divergence
 * would show up as an ability offered on one and not the other.
 */
export function ApiTokenForm({ values, setValue, errors, availableAbilities, maxDays }: Props) {
    const { t } = useTranslation();

    const allKeys = availableAbilities.flatMap((group) => group.abilities.map((ability) => ability.key));
    const allSelected = allKeys.length > 0 && allKeys.every((key) => values.abilities.includes(key));

    const toggleAbility = (key: string, checked: boolean) => {
        setValue('abilities', checked ? [...values.abilities, key] : values.abilities.filter((ability) => ability !== key));
    };

    const toggleGroup = (group: AbilityGroup, checked: boolean) => {
        const keys = group.abilities.map((ability) => ability.key);

        setValue('abilities', checked ? [...new Set([...values.abilities, ...keys])] : values.abilities.filter((ability) => !keys.includes(ability)));
    };

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Token name')}</Label>
                <Input
                    id="name"
                    value={values.name}
                    onChange={(e) => setValue('name', e.target.value)}
                    placeholder={t('e.g. Zapier')}
                    autoComplete="off"
                />
                <p className="text-muted-foreground text-sm">
                    {t('Name it after the tool that will use it, so you know what you are revoking later.')}
                </p>
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <div className="flex items-center justify-between gap-4">
                    <Label>{t('Permissions')}</Label>
                    {allKeys.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-auto px-2 py-1 text-xs"
                            onClick={() => setValue('abilities', allSelected ? [] : allKeys)}
                        >
                            {allSelected ? t('Clear all') : t('Select all')}
                        </Button>
                    )}
                </div>
                <p className="text-muted-foreground text-sm">
                    {t('A token can never do more than you can. Grant only what the tool needs — if your own permissions change, the token follows.')}
                </p>
                <p className="text-muted-foreground text-sm">
                    {t('Only permissions the API can currently act on are listed. More appear here as new endpoints are released.')}
                </p>

                {availableAbilities.length === 0 && (
                    <p className="text-muted-foreground text-sm">
                        {t('There is nothing you can grant a token yet — none of the API endpoints released so far match your permissions.')}
                    </p>
                )}

                <div className="space-y-4">
                    {availableAbilities.map((group) => {
                        const groupSelected = group.abilities.every((ability) => values.abilities.includes(ability.key));

                        return (
                            <div key={group.category} className="space-y-2">
                                <div className="flex items-center justify-between gap-4">
                                    <p className="text-sm font-medium">{t(group.label)}</p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-muted-foreground h-auto px-2 py-1 text-xs"
                                        onClick={() => toggleGroup(group, !groupSelected)}
                                    >
                                        {groupSelected ? t('Clear all') : t('Select all')}
                                    </Button>
                                </div>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {group.abilities.map((ability) => (
                                        <div key={ability.key} className="flex items-center gap-2">
                                            <Checkbox
                                                id={`ability-${ability.key}`}
                                                checked={values.abilities.includes(ability.key)}
                                                onCheckedChange={(checked) => toggleAbility(ability.key, checked === true)}
                                            />
                                            <Label htmlFor={`ability-${ability.key}`} className="text-sm font-normal">
                                                {t(ability.label)}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>

                <InputError message={errors.abilities ?? errors['abilities.0']} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="expires_in_days">{t('Expires in (days)')}</Label>
                <Input
                    id="expires_in_days"
                    type="number"
                    min={1}
                    max={maxDays}
                    value={values.expires_in_days}
                    disabled={values.never_expires}
                    onChange={(e) => setValue('expires_in_days', Number(e.target.value))}
                    className="max-w-32"
                />
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="never_expires"
                        checked={values.never_expires}
                        onCheckedChange={(checked) => setValue('never_expires', checked === true)}
                    />
                    <Label htmlFor="never_expires" className="text-sm font-normal">
                        {t('Never expires')}
                    </Label>
                </div>
                {values.never_expires && (
                    <p className="text-sm text-amber-600 dark:text-amber-500">
                        {t('A token that never expires stays valid until someone revokes it by hand. Prefer a date you can forget about safely.')}
                    </p>
                )}
                <InputError message={errors.expires_in_days} />
            </div>
        </>
    );
}

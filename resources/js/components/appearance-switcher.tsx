import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Appearance, useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';
import { Check, Monitor, Moon, Sun } from 'lucide-react';

function useAppearanceOptions() {
    const { t } = useTranslation();
    const { appearance, updateAppearance } = useAppearance();

    const options: { value: Appearance; label: string; icon: typeof Sun }[] = [
        { value: 'light', label: t('Light'), icon: Sun },
        { value: 'dark', label: t('Dark'), icon: Moon },
        { value: 'system', label: t('System'), icon: Monitor },
    ];

    const current = options.find((option) => option.value === appearance) ?? options[2];

    return { options, current, appearance, updateAppearance };
}

/** Icon-only trigger for header bars — the dropdown content carries the labels. */
export function AppearanceSwitcher() {
    const { t } = useTranslation();
    const { options, current, appearance, updateAppearance } = useAppearanceOptions();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label={t('Appearance')}>
                    <current.icon className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {options.map((option) => (
                    <DropdownMenuItem key={option.value} onClick={() => updateAppearance(option.value)}>
                        <option.icon className="size-4" />
                        {option.label}
                        {option.value === appearance && <Check className="ml-auto size-4" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

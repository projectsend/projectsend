import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface SecuritySettingsProps {
    two_factor_enforcement: string;
    password_min_length: number;
    password_reject_breached: boolean;
    password_min_length_floor: number;
    password_min_length_ceiling: number;
}

export default function SecuritySettings({
    two_factor_enforcement,
    password_min_length,
    password_reject_breached,
    password_min_length_floor,
    password_min_length_ceiling,
}: SecuritySettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Settings'),
            href: '/system/settings',
        },
        {
            title: t('Security'),
            href: '/system/settings/security',
        },
    ];

    const options: { value: string; label: string }[] = [
        { value: 'none', label: t('Nobody (optional for everyone)') },
        { value: 'staff', label: t('All system users') },
        { value: 'clients', label: t('All clients') },
        { value: 'all', label: t('Everyone') },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        two_factor_enforcement: two_factor_enforcement,
        password_min_length: password_min_length,
        password_reject_breached: password_reject_breached,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.security.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Security settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Security settings')} description={t('Authentication requirements for this installation')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="two_factor_enforcement">{t('Require two-factor authentication')}</Label>

                        <Select value={data.two_factor_enforcement} onValueChange={(value) => setData('two_factor_enforcement', value)}>
                            <SelectTrigger id="two_factor_enforcement" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Accounts covered by this requirement must set up two-factor authentication before they can continue using the application.',
                            )}
                        </p>

                        <InputError className="mt-2" message={errors.two_factor_enforcement} />
                    </div>

                    <div className="space-y-6 border-t pt-6">
                        <Heading title={t('Passwords')} description={t('Applies whenever a password is chosen, not when one is used to sign in')} />

                        <div className="grid gap-2">
                            <Label htmlFor="password_min_length">{t('Minimum length')}</Label>
                            <Input
                                id="password_min_length"
                                type="number"
                                min={password_min_length_floor}
                                max={password_min_length_ceiling}
                                className="max-w-32"
                                value={data.password_min_length}
                                onChange={(e) => setData('password_min_length', Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Length is the single biggest factor in how hard a password is to guess, which is why there are no "must contain a capital letter" rules here — they push people towards predictable substitutions and shorter passwords.',
                                )}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {t('Existing passwords are never re-checked, so raising this only affects passwords chosen from now on.')}
                            </p>
                            <InputError className="mt-2" message={errors.password_min_length} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="password_reject_breached"
                                    checked={data.password_reject_breached}
                                    onCheckedChange={(checked) => setData('password_reject_breached', checked === true)}
                                />
                                <Label htmlFor="password_reject_breached" className="font-normal">
                                    {t('Reject passwords found in known data breaches')}
                                </Label>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Checks new passwords against the Have I Been Pwned breach corpus. The password itself never leaves this server — only the first five characters of its hash are sent.',
                                )}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Turn this off only if this server should make no outbound requests. If the service cannot be reached the password is accepted anyway, so an offline installation is never blocked by it.',
                                )}
                            </p>
                            <InputError className="mt-2" message={errors.password_reject_breached} />
                        </div>
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}

import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

import HeadingSmall from '@/components/heading-small';
import { SaveButton } from '@/components/save-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordRequirements } from '@/components/password-requirements';
import { useTranslation } from '@/hooks/use-translation';

interface PasswordProps {
    /** False for an account that signs in through a provider and has never had a password here. */
    has_local_password: boolean;
    /** True for an account whose password lives in a directory, which this screen cannot change. */
    managed_elsewhere: boolean;
}

export default function Password({ has_local_password, managed_elsewhere }: PasswordProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Password settings'),
            href: '/settings/password',
        },
    ];

    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Password settings')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={has_local_password ? t('Update password') : t('Set a password')}
                        description={
                            has_local_password
                                ? t('Ensure your account is using a long, random password to stay secure')
                                : t('You sign in through a connected account. A password of your own lets you sign in without it, and is needed to turn on two-factor authentication.')
                        }
                    />

                    {managed_elsewhere && (
                        <p className="text-muted-foreground text-sm">
                            {t('Your password is managed by your organisation\'s directory, so it cannot be changed here.')}
                        </p>
                    )}

                    <form onSubmit={updatePassword} className="space-y-6" hidden={managed_elsewhere}>
                        <div className="grid gap-2" hidden={!has_local_password}>
                            <Label htmlFor="current_password">{t('Current password')}</Label>

                            <Input
                                id="current_password"
                                ref={currentPasswordInput}
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="current-password"
                                placeholder={t('Current password')}
                            />

                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('New password')}</Label>

                            <Input
                                id="password"
                                ref={passwordInput}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder={t('New password')}
                            />

                            <PasswordRequirements />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">{t('Confirm password')}</Label>

                            <Input
                                id="password_confirmation"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder={t('Confirm password')}
                            />

                            <InputError message={errors.password_confirmation} />
                        </div>

                        <SaveButton processing={processing} recentlySuccessful={recentlySuccessful}>
                            {t('Save password')}
                        </SaveButton>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

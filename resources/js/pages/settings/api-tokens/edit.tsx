import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ApiTokenForm, type AbilityGroup, type TokenFormValues } from '@/components/api-token-form';
import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';

interface EditableToken {
    id: string;
    name: string;
    abilities: string[];
    expires_at: string | null;
    created_at: string | null;
    last_used_at: string | null;
    retired_abilities: string[];
}

interface Props {
    token: EditableToken;
    available_abilities: AbilityGroup[];
    defaults: { expires_in_days: number; max_days: number };
}

export default function ApiTokenEdit({ token, available_abilities, defaults }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('API tokens'), href: '/settings/api-tokens' },
        { title: token.name, href: `/settings/api-tokens/${token.id}/edit` },
    ];

    const remainingDays = token.expires_at
        ? Math.max(1, Math.ceil((new Date(token.expires_at).getTime() - Date.now()) / 86_400_000))
        : defaults.expires_in_days;

    const { data, setData, patch, processing, errors } = useForm<TokenFormValues>({
        name: token.name,
        abilities: token.abilities,
        expires_in_days: remainingDays,
        never_expires: token.expires_at === null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('api-tokens.update', token.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit token')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Edit token')}
                        description={t('The token itself does not change. Anything already using it keeps working with whatever you set here.')}
                    />

                    <Alert>
                        <AlertTitle>{t('Changing permissions takes effect immediately')}</AlertTitle>
                        <AlertDescription>
                            {t(
                                'The existing token keeps its value, so whatever holds it gains or loses these permissions right away. Revoke and create a new one instead if you want a fresh secret.',
                            )}
                        </AlertDescription>
                    </Alert>

                    {token.retired_abilities.length > 0 && (
                        <Alert>
                            <AlertTitle>{t('Some permissions no longer apply')}</AlertTitle>
                            <AlertDescription>
                                {t('This token still carries permissions that do nothing today, and saving will drop them: :abilities', {
                                    abilities: token.retired_abilities.join(', '),
                                })}
                            </AlertDescription>
                        </Alert>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <ApiTokenForm
                            values={data}
                            setValue={setData}
                            errors={errors}
                            availableAbilities={available_abilities}
                            maxDays={defaults.max_days}
                        />

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing}>
                                {t('Save token')}
                            </Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('api-tokens.index')}>{t('Cancel')}</Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

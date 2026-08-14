import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ApiTokenForm, type AbilityGroup, type TokenFormValues } from '@/components/api-token-form';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';

interface Props {
    available_abilities: AbilityGroup[];
    defaults: { expires_in_days: number; max_days: number };
}

export default function ApiTokenCreate({ available_abilities, defaults }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('API tokens'), href: '/settings/api-tokens' },
        { title: t('Create token'), href: '/settings/api-tokens/create' },
    ];

    const { data, setData, post, processing, errors } = useForm<TokenFormValues>({
        name: '',
        abilities: [],
        expires_in_days: defaults.expires_in_days,
        never_expires: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('api-tokens.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Create token')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Create token')}
                        description={t('The token is shown once, immediately after it is created, and never again.')}
                    />

                    <form onSubmit={submit} className="space-y-6">
                        <ApiTokenForm
                            values={data}
                            setValue={setData}
                            errors={errors}
                            availableAbilities={available_abilities}
                            maxDays={defaults.max_days}
                        />

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing || available_abilities.length === 0}>
                                {t('Create token')}
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

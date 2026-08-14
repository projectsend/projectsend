import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

interface NotificationTypePreference {
    key: string;
    label: string;
    email_enabled: boolean;
}

export default function NotificationPreferences({ types }: { types: NotificationTypePreference[] }) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Notification settings'),
            href: '/settings/notifications',
        },
    ];

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        preferences: types.map((type) => ({ type: type.key, email_enabled: type.email_enabled })),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('notification-preferences.update'));
    };

    const toggle = (key: string, checked: boolean) => {
        setData(
            'preferences',
            data.preferences.map((preference) => (preference.type === key ? { ...preference, email_enabled: checked } : preference)),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Notification settings')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Notifications')}
                        description={t('Choose which notifications also send you an email. Everything always appears in the notification bell.')}
                    />

                    <form onSubmit={submit} className="space-y-6">
                        {types.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('There is nothing to configure yet.')}</p>
                        ) : (
                            <div className="space-y-4">
                                {types.map((type) => {
                                    const current = data.preferences.find((preference) => preference.type === type.key);

                                    return (
                                        <div key={type.key} className="flex items-center gap-3">
                                            <Checkbox
                                                id={`notification-${type.key}`}
                                                checked={current?.email_enabled ?? false}
                                                onCheckedChange={(checked) => toggle(type.key, checked === true)}
                                            />
                                            <Label htmlFor={`notification-${type.key}`} className="font-normal">
                                                {t(type.label)}
                                            </Label>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                {t('Save')}
                            </Button>

                            {recentlySuccessful && <p className="text-muted-foreground text-sm">{t('Saved')}</p>}
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

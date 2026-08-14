import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ClientCustomFieldsSection, type CustomFieldDefinition } from '@/components/client-custom-fields-section';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { TimezonePicker, type TimezoneOption } from '@/components/timezone-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
    custom_fields,
    custom_field_values,
    timezone,
    timezones,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    custom_fields: CustomFieldDefinition[];
    custom_field_values: Record<string, string>;
    timezone: string;
    timezones: TimezoneOption[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
        custom_field_values,
        timezone,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Profile information" description="Update your name and email address" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>

                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                                placeholder="Full name"
                            />

                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>

                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoComplete="username"
                                placeholder="Email address"
                            />

                            <InputError className="mt-2" message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="timezone">{t('Timezone')}</Label>

                            <TimezonePicker options={timezones} value={data.timezone} onChange={(tz) => setData('timezone', tz)} />

                            <p className="text-muted-foreground text-sm">
                                {t('Dates and times across the app are shown in this zone. Detected from your browser the first time you signed in.')}
                            </p>

                            <InputError className="mt-2" message={errors.timezone} />
                        </div>

                        <ClientCustomFieldsSection
                            fields={custom_fields}
                            values={data.custom_field_values}
                            onChange={(fieldId, value) => setData('custom_field_values', { ...data.custom_field_values, [fieldId]: value })}
                            errors={errors}
                        />

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <div>
                                <p className="mt-2 text-sm text-neutral-800">
                                    Your email address is unverified.
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                                    >
                                        Click here to re-send the verification email.
                                    </Link>
                                </p>

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-green-600">
                                        A new verification link has been sent to your email address.
                                    </div>
                                )}
                            </div>
                        )}

                        <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { TimezonePicker, type TimezoneOption } from '@/components/timezone-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface SystemSettingsProps {
    site_name: string;
    timezone: string;
    timezones: TimezoneOption[];
    /** Null unless this administrator has picked a timezone of their own. */
    viewer_timezone: string | null;
    can_manage_updates: boolean;
    check_for_updates: boolean | null;
}

export default function SystemSettings({
    site_name,
    timezone,
    timezones,
    viewer_timezone,
    can_manage_updates,
    check_for_updates,
}: SystemSettingsProps) {
    const { t } = useTranslation();
    const { version, links } = usePage<SharedData>().props;

    // Matched against the offered list so the note names the zone the way
    // the picker does, rather than printing a raw identifier.
    const viewerZone = viewer_timezone === null ? undefined : timezones.find((zone) => zone.value === viewer_timezone);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Settings'),
            href: '/system/settings',
        },
        {
            title: t('General'),
            href: '/system/settings/general',
        },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        site_name: site_name,
        timezone: timezone,
        check_for_updates: check_for_updates ?? false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('General settings')} />

            <div className="px-4 py-6">
                <Heading title={t('General settings')} description={t('Settings that apply to the whole installation')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="site_name">{t('Site name')}</Label>

                        <Input
                            id="site_name"
                            className="mt-1 block w-full"
                            value={data.site_name}
                            onChange={(e) => setData('site_name', e.target.value)}
                            required
                        />

                        <p className="text-muted-foreground text-sm">{t('Shown across the application and to your clients.')}</p>

                        <InputError className="mt-2" message={errors.site_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="timezone">{t('Timezone')}</Label>

                        <TimezonePicker options={timezones} value={data.timezone} onChange={(tz) => setData('timezone', tz)} />

                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Used for anyone who has not chosen a timezone of their own, including visitors to your public pages and share links. Each account can set its own in their profile.',
                            )}
                        </p>

                        {viewerZone && (
                            <p className="text-muted-foreground text-sm">
                                {t('Your own account is set to :timezone, so this will not change how dates look to you.', {
                                    timezone: viewerZone.label,
                                })}{' '}
                                <Link href="/settings/profile" className="underline underline-offset-4">
                                    {t('Change yours')}
                                </Link>
                            </p>
                        )}

                        <InputError className="mt-2" message={errors.timezone} />
                    </div>

                    {can_manage_updates && (
                        <div className="grid gap-2">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="check_for_updates"
                                    checked={data.check_for_updates}
                                    onCheckedChange={(checked) => setData('check_for_updates', checked === true)}
                                />
                                <Label htmlFor="check_for_updates">{t('Check for updates')}</Label>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    "Periodically checks projectsend.org's public repository for a newer release and shows a notice on the dashboard. Nothing is ever downloaded or applied automatically — updating is always something you run, and the update script asks before each step.",
                                )}
                            </p>
                        </div>
                    )}

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>

                <p className="text-muted-foreground/70 mt-10 text-xs">
                    ProjectSend {version} ·{' '}
                    <a href={links.website} target="_blank" rel="noreferrer" className="hover:text-muted-foreground underline">
                        projectsend.org
                    </a>{' '}
                    ·{' '}
                    <a href={links.open_collective} target="_blank" rel="noreferrer" className="hover:text-muted-foreground underline">
                        {t('Support the project')}
                    </a>
                </p>
            </div>
        </AppLayout>
    );
}

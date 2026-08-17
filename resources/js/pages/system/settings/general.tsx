import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { TestResultAlert } from '@/components/test-result-alert';
import { TimezonePicker, type TimezoneOption } from '@/components/timezone-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormatDate } from '@/hooks/use-format-date';
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
    /** When the release feed was last asked, by anybody. Null until it has been. */
    last_checked_at: string | null;
    /** The answer to a "check now" press, for the one render after it. */
    check_result: { ok: boolean; message: string } | null;
}

export default function SystemSettings({
    site_name,
    timezone,
    timezones,
    viewer_timezone,
    can_manage_updates,
    check_for_updates,
    last_checked_at,
    check_result,
}: SystemSettingsProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const { version, links } = usePage<SharedData>().props;
    const [checking, setChecking] = useState(false);

    const checkNow = () => {
        router.post(
            route('system-settings.check-for-updates'),
            {},
            {
                preserveScroll: true,
                onStart: () => setChecking(true),
                onFinish: () => setChecking(false),
            },
        );
    };

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

                            {/* Outside the form on purpose: this asks a
                                question rather than saving anything, and
                                pressing it should not depend on — or
                                discard — unsaved edits above it. */}
                            <div className="mt-1 flex flex-wrap items-center gap-3">
                                <Button type="button" variant="outline" onClick={checkNow} disabled={checking}>
                                    {checking ? t('Checking…') : t('Check now')}
                                </Button>
                                {last_checked_at && (
                                    <span className="text-muted-foreground text-sm">
                                        {t('Last checked :date', { date: dateTime(last_checked_at) })}
                                    </span>
                                )}
                            </div>

                            {check_result && <TestResultAlert ok={check_result.ok}>{check_result.message}</TestResultAlert>}
                        </div>
                    )}

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>

                <p className="text-muted-foreground/70 mt-10 text-xs">
                    ProjectSend {version} ·{' '}
                    {/* The host, not a hardcoded "projectsend.org": each
                        edition has its own front door and this line used to
                        name the wrong one on the hosted service. */}
                    <a href={links.website} target="_blank" rel="noreferrer" className="hover:text-muted-foreground underline">
                        {new URL(links.website).host.replace(/^www\./, '')}
                    </a>
                    {links.open_collective && (
                        <>
                            {' '}
                            ·{' '}
                            <a
                                href={links.open_collective}
                                target="_blank"
                                rel="noreferrer"
                                className="hover:text-muted-foreground underline"
                            >
                                {t('Support the project')}
                            </a>
                        </>
                    )}
                </p>
            </div>
        </AppLayout>
    );
}

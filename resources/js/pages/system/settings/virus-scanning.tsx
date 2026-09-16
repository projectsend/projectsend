import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ShieldAlert, TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { VirusScanActivity } from '@/components/virus-scan-activity';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

type Tab = 'scanner' | 'options' | 'activity';

interface VirusScanningProps {
    tab: Tab;
    /** The Test button's answer, carried through the session. */
    test_result: { ok: boolean; message: string } | null;
    enabled: boolean;
    /** The scanner is supplied by the platform: no address to set, and no switch. */
    managed: boolean;
    address: string;
    max_size_mb: number;
    unscannable_policy: 'allow' | 'block';
    scanner_down_policy: 'allow' | 'hold';
    wait_minutes: number;
    existing_rate_per_minute: number;
    counts: {
        pending: number;
        quarantined: number;
        never_scanned: number;
        let_through: number;
    };
}

export default function VirusScanningSettings({
    tab,
    test_result,
    enabled,
    managed,
    address,
    max_size_mb,
    unscannable_policy,
    scanner_down_policy,
    wait_minutes,
    existing_rate_per_minute,
    counts,
}: VirusScanningProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Virus scanning'), href: '/system/settings/virus-scanning' },
    ];

    // One form behind both tabs, and one Save. The server takes every
    // field on every save, so switching tabs never loses what was typed on
    // the other one.
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        enabled,
        address,
        max_size_mb,
        unscannable_policy,
        scanner_down_policy,
        wait_minutes,
        existing_rate_per_minute,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('system-settings.virus-scanning.update', tab === 'options' ? { tab: 'options' } : {}), { preserveScroll: true });
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: 'scanner', label: t('Scanner') },
        { key: 'options', label: t('Options') },
        { key: 'activity', label: t('Activity') },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Virus scanning')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Virus scanning')} description={t('Uploaded files are checked before anyone can download them')} />

                    {/* The screen this one leads to: whatever the scanner
                        actually refused. Only for somebody who may act on
                        it — the quarantine screen answers 403 otherwise,
                        and a button that leads to a refusal is worse than
                        no button. */}
                    {auth.permissions.includes('release_quarantined_files') && (
                        <Button variant="outline" asChild>
                            <Link href={route('files.quarantine')}>
                                {counts.quarantined > 0
                                    ? t('Quarantine (:count)', { count: counts.quarantined })
                                    : t('Quarantine')}
                            </Link>
                        </Button>
                    )}
                </div>

                {counts.let_through > 0 && (
                    <Alert variant="destructive" className="max-w-xl">
                        <TriangleAlert className="size-4" />
                        <AlertTitle>{t(':count files were allowed through without being scanned', { count: counts.let_through })}</AlertTitle>
                        <AlertDescription>
                            {t(
                                'They are marked "not scanned" and can be downloaded. This happens when a file is too large or encrypted, or when the scanner could not be reached.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="border-border flex gap-1 border-b">
                    {tabs.map(({ key, label }) => (
                        <Link
                            key={key}
                            href={route('system-settings.virus-scanning.edit', key === 'scanner' ? {} : { tab: key })}
                            preserveScroll
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${
                                tab === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'
                            }`}
                        >
                            {label}
                        </Link>
                    ))}
                </div>

                {tab === 'scanner' && (
                    <div className="max-w-xl space-y-6">
                        {/* Above the form on purpose: these act on the scanner
                            as it is now, and nothing belongs after a Save
                            button. */}
                        <div className="space-y-3 rounded-lg border p-4">
                            <HeadingSmall
                                title={t('Check the connection')}
                                description={t('Sends the standard test file, which is harmless and every scanner recognises.')}
                            />

                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                onClick={() => router.post(route('system-settings.virus-scanning.test'), {}, { preserveScroll: true })}
                            >
                                {t('Test scanner')}
                            </Button>

                            {test_result && (
                                <Alert variant={test_result.ok ? 'default' : 'destructive'}>
                                    {test_result.ok ? <CheckCircle2 className="size-4" /> : <TriangleAlert className="size-4" />}
                                    <AlertDescription>{test_result.message}</AlertDescription>
                                </Alert>
                            )}
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            {managed ? (
                                <Alert>
                                    <ShieldAlert className="size-4" />
                                    <AlertTitle>{t('Scanning is managed for you')}</AlertTitle>
                                    <AlertDescription>
                                        {t(
                                            'Every upload on this site is scanned. The scanner itself is run for you, so there is nothing to connect here.',
                                        )}
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="enabled"
                                                checked={data.enabled}
                                                onCheckedChange={(checked) => setData('enabled', checked === true)}
                                            />
                                            <Label htmlFor="enabled" className="font-normal">
                                                {t('Scan uploaded files for viruses')}
                                            </Label>
                                        </div>
                                        <InputError message={errors.enabled} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="address">{t('Scanner address')}</Label>
                                        <Input
                                            id="address"
                                            value={data.address}
                                            onChange={(e) => setData('address', e.target.value)}
                                            placeholder="tcp://clamav:3310"
                                        />
                                        <p className="text-muted-foreground text-sm">
                                            {t('A ClamAV daemon, as tcp://host:3310 or unix:///path/to/clamd.sock.')}
                                        </p>
                                        <InputError message={errors.address} />
                                    </div>
                                </>
                            )}

                            <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                        </form>
                    </div>
                )}

                {tab === 'options' && (
                    <div className="max-w-xl space-y-6">
                        <div className="space-y-3 rounded-lg border p-4">
                            <HeadingSmall
                                title={t('Files already here')}
                                description={t('Anything uploaded before scanning was switched on has never been checked.')}
                            />

                            <p className="text-muted-foreground text-sm">
                                {t('Never scanned: :never · Being checked: :pending · In quarantine: :quarantined', {
                                    never: counts.never_scanned,
                                    pending: counts.pending,
                                    quarantined: counts.quarantined,
                                })}
                            </p>

                            <Button
                                type="button"
                                variant="outline"
                                disabled={!enabled || counts.never_scanned === 0}
                                onClick={() => router.post(route('system-settings.virus-scanning.scan-existing'), {}, { preserveScroll: true })}
                            >
                                {t('Scan existing files')}
                            </Button>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="max_size_mb">{t('Largest file to scan (MB)')}</Label>
                                <Input
                                    id="max_size_mb"
                                    type="number"
                                    min={0}
                                    max={4096}
                                    value={data.max_size_mb}
                                    onChange={(e) => setData('max_size_mb', Number(e.target.value))}
                                />
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'Bigger files are handled by the rule below. Your scanner has its own limit too, and this should not exceed it.',
                                    )}
                                </p>
                                <InputError message={errors.max_size_mb} />
                            </div>

                            <div className="grid gap-3">
                                <HeadingSmall
                                    title={t('Files that cannot be scanned')}
                                    description={t('Too large, or an encrypted archive or document the scanner cannot open.')}
                                />
                                <Select
                                    value={data.unscannable_policy}
                                    onValueChange={(value: string) => setData('unscannable_policy', value as 'allow' | 'block')}
                                >
                                    <SelectTrigger id="unscannable_policy" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="allow">{t('Allow them, marked "not scanned"')}</SelectItem>
                                        <SelectItem value="block">{t('Block them, like an infected file')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.unscannable_policy} />
                            </div>

                            <div className="grid gap-3">
                                <HeadingSmall
                                    title={t('If the scanner cannot be reached')}
                                    description={t('What happens to new uploads while the scanner is down.')}
                                />
                                <Select
                                    value={data.scanner_down_policy}
                                    onValueChange={(value: string) => setData('scanner_down_policy', value as 'allow' | 'hold')}
                                >
                                    <SelectTrigger id="scanner_down_policy" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="allow">{t('Allow them after the wait below, marked "not scanned"')}</SelectItem>
                                        <SelectItem value="hold">{t('Hold them until the scanner is back')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-sm">
                                    {t('Files allowed through this way are scanned again automatically once the scanner answers.')}
                                </p>
                                <InputError message={errors.scanner_down_policy} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="wait_minutes">{t('How long to wait for the scanner (minutes)')}</Label>
                                <Input
                                    id="wait_minutes"
                                    type="number"
                                    min={1}
                                    max={1440}
                                    value={data.wait_minutes}
                                    onChange={(e) => setData('wait_minutes', Number(e.target.value))}
                                />
                                <InputError message={errors.wait_minutes} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="existing_rate_per_minute">{t('Files to scan per minute')}</Label>
                                <Input
                                    id="existing_rate_per_minute"
                                    type="number"
                                    min={1}
                                    max={6000}
                                    className="w-32"
                                    value={data.existing_rate_per_minute}
                                    onChange={(e) => setData('existing_rate_per_minute', Number(e.target.value))}
                                />
                                <p className="text-muted-foreground text-sm">
                                    {t('Applies to the button above, so a backfill does not starve the scanner of new uploads.')}
                                </p>
                                <InputError message={errors.existing_rate_per_minute} />
                            </div>

                            <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                        </form>
                    </div>
                )}
                {/* Mounted only while the tab is open, which is also what
                    starts and stops its polling. */}
                {tab === 'activity' && <VirusScanActivity />}
            </div>
        </AppLayout>
    );
}

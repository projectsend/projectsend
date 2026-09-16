import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
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
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface VirusScanningProps {
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
    const testResult = usePage().props.scanner_test_result as { ok: boolean; message: string } | undefined;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Virus scanning'), href: '/system/settings/virus-scanning' },
    ];

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
        patch(route('system-settings.virus-scanning.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Virus scanning')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Virus scanning')}
                    description={t('Uploaded files are checked before anyone can download them')}
                />

                {counts.let_through > 0 && (
                    <Alert variant="destructive" className="mb-6 max-w-xl">
                        <TriangleAlert className="size-4" />
                        <AlertTitle>{t(':count files were allowed through without being scanned', { count: counts.let_through })}</AlertTitle>
                        <AlertDescription>
                            {t(
                                'They are marked "not scanned" and can be downloaded. This happens when a file is too large or encrypted, or when the scanner could not be reached.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    {managed ? (
                        <Alert className="max-w-xl">
                            <ShieldAlert className="size-4" />
                            <AlertTitle>{t('Scanning is managed for you')}</AlertTitle>
                            <AlertDescription>
                                {t('Every upload on this site is scanned. The scanner itself is run for you, so there is nothing to connect here.')}
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

                            <div className="grid gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() => router.post(route('system-settings.virus-scanning.test'), {}, { preserveScroll: true })}
                                >
                                    {t('Test scanner')}
                                </Button>
                                <p className="text-muted-foreground text-sm">
                                    {t('Sends the standard test file, which is harmless and every scanner recognises.')}
                                </p>
                                {testResult && (
                                    <Alert variant={testResult.ok ? 'default' : 'destructive'}>
                                        {testResult.ok ? <CheckCircle2 className="size-4" /> : <TriangleAlert className="size-4" />}
                                        <AlertDescription>{testResult.message}</AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        </>
                    )}

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
                            {t('Bigger files are handled by the rule below. Your scanner has its own limit too, and this should not exceed it.')}
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

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>

                <div className="mt-10 max-w-xl space-y-3">
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
                        <p className="text-muted-foreground text-sm">{t('Kept low so the scanner stays free for new uploads. Save first.')}</p>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        disabled={!enabled || counts.never_scanned === 0}
                        onClick={() => router.post(route('system-settings.virus-scanning.scan-existing'), {}, { preserveScroll: true })}
                    >
                        {t('Scan existing files')}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}

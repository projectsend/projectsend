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

interface ReassignCandidate {
    id: number;
    name: string;
    role: string;
}

interface PrivacySettingsProps {
    download_ip_logging: string;
    account_erasure_grace_days: number;
    account_erasure_content_action: string;
    account_erasure_reassign_to: number;
    reassign_candidates: ReassignCandidate[];
    api_request_log_retention_days: number;
    discourage_search_indexing: boolean;
}

export default function PrivacySettings({
    download_ip_logging,
    account_erasure_grace_days,
    account_erasure_content_action,
    account_erasure_reassign_to,
    reassign_candidates,
    api_request_log_retention_days,
    discourage_search_indexing,
}: PrivacySettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Privacy'), href: '/system/settings/privacy' },
    ];

    const ipOptions: { value: string; label: string; description: string }[] = [
        { value: 'all', label: t('Always'), description: t('Every download records the IP address of whoever downloaded it.') },
        {
            value: 'anonymous_only',
            label: t('Only for anonymous downloads'),
            description: t('Skip the IP address when the downloader is a signed-in account — their identity is already known.'),
        },
        { value: 'none', label: t('Never'), description: t('Downloads are never recorded with an IP address.') },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        download_ip_logging: download_ip_logging,
        account_erasure_grace_days: account_erasure_grace_days,
        account_erasure_content_action: account_erasure_content_action,
        account_erasure_reassign_to: account_erasure_reassign_to ? String(account_erasure_reassign_to) : '',
        api_request_log_retention_days: api_request_log_retention_days,
        discourage_search_indexing: discourage_search_indexing,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.privacy.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Privacy settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Privacy settings')} description={t('Data retention and visibility for this installation')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="download_ip_logging">{t('Record IP addresses for downloads')}</Label>

                        <Select value={data.download_ip_logging} onValueChange={(value) => setData('download_ip_logging', value)}>
                            <SelectTrigger id="download_ip_logging" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ipOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <p className="text-muted-foreground text-sm">
                            {ipOptions.find((option) => option.value === data.download_ip_logging)?.description}
                        </p>

                        <InputError className="mt-2" message={errors.download_ip_logging} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="account_erasure_grace_days">{t('Account deletion grace period (days)')}</Label>
                        <Input
                            id="account_erasure_grace_days"
                            type="number"
                            min={0}
                            className="max-w-32"
                            value={data.account_erasure_grace_days}
                            onChange={(e) => setData('account_erasure_grace_days', Number(e.target.value))}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t('How long a self-deleted account is kept before it is permanently erased.')}
                        </p>
                        <InputError className="mt-2" message={errors.account_erasure_grace_days} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="account_erasure_content_action">{t('When an account is erased, its files and folders')}</Label>

                        <Select
                            value={data.account_erasure_content_action}
                            onValueChange={(value) => setData('account_erasure_content_action', value)}
                        >
                            <SelectTrigger id="account_erasure_content_action" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cascade_delete">{t('Are deleted along with the account')}</SelectItem>
                                <SelectItem value="reassign">{t('Are reassigned to another account')}</SelectItem>
                            </SelectContent>
                        </Select>

                        <p className="text-muted-foreground text-sm">
                            {t(
                                'An administrator deleting an account is asked this each time; the automatic erasure of self-deleted accounts runs unattended and follows this choice, so nothing is ever left ownerless.',
                            )}
                        </p>

                        <InputError className="mt-2" message={errors.account_erasure_content_action} />
                    </div>

                    {data.account_erasure_content_action === 'reassign' && (
                        <div className="grid gap-2">
                            <Label htmlFor="account_erasure_reassign_to">{t('Reassign them to')}</Label>

                            <Select
                                value={data.account_erasure_reassign_to}
                                onValueChange={(value) => setData('account_erasure_reassign_to', value)}
                            >
                                <SelectTrigger id="account_erasure_reassign_to" className="w-full">
                                    <SelectValue placeholder={t('Choose an account…')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {reassign_candidates.map((candidate) => (
                                        <SelectItem key={candidate.id} value={String(candidate.id)}>
                                            {candidate.name} — {candidate.role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'If this account is no longer available when an erasure runs, the files are deleted instead of being left ownerless.',
                                )}
                            </p>

                            <InputError className="mt-2" message={errors.account_erasure_reassign_to} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="api_request_log_retention_days">{t('API request history (days)')}</Label>
                        <Input
                            id="api_request_log_retention_days"
                            type="number"
                            min={0}
                            className="max-w-32"
                            value={data.api_request_log_retention_days}
                            onChange={(e) => setData('api_request_log_retention_days', Number(e.target.value))}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'How long the API dashboard keeps a record of each request. This is usage history, not the activity log — the activity log is an audit trail and is never pruned. Set to 0 to keep it indefinitely.',
                            )}
                        </p>
                        <InputError className="mt-2" message={errors.api_request_log_retention_days} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="discourage_search_indexing"
                                checked={data.discourage_search_indexing}
                                onCheckedChange={(checked) => setData('discourage_search_indexing', checked === true)}
                            />
                            <Label htmlFor="discourage_search_indexing" className="font-normal">
                                {t('Discourage search engines from indexing this site')}
                            </Label>
                        </div>
                        <InputError className="mt-2" message={errors.discourage_search_indexing} />
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}

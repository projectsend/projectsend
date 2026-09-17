import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface ClientSettingsProps {
    clients_can_register: boolean;
    clients_auto_approve: boolean;
    clients_auto_group: number;
    clients_can_select_group: string;
    clients_membership_deny_cooldown_days: number;
    client_invitation_expiry_hours: number;
    default_client_storage_quota_mb: number;
    clients_can_preview_files: boolean;
    clients_home_folders: boolean;
    clients_without_home: number;
    groups: { id: number; name: string }[];
}

export default function ClientSettings({
    clients_can_register,
    clients_auto_approve,
    clients_auto_group,
    clients_can_select_group,
    clients_membership_deny_cooldown_days,
    client_invitation_expiry_hours,
    default_client_storage_quota_mb,
    clients_can_preview_files,
    clients_home_folders,
    clients_without_home,
    groups,
}: ClientSettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Clients'), href: '/system/settings/clients' },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        clients_can_register: clients_can_register,
        clients_auto_approve: clients_auto_approve,
        clients_auto_group: String(clients_auto_group),
        clients_can_select_group: clients_can_select_group,
        clients_membership_deny_cooldown_days: String(clients_membership_deny_cooldown_days),
        client_invitation_expiry_hours: String(client_invitation_expiry_hours),
        default_client_storage_quota_mb: String(default_client_storage_quota_mb),
        clients_can_preview_files: clients_can_preview_files,
        clients_home_folders: clients_home_folders,
    });

    // Its own request, and its own spinner. Saving the form records the
    // preference; this writes a folder per client, so the two must not look
    // like one action.
    const backfill = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('system-settings.clients.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Client settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Client settings')} description={t('How client accounts are created on this installation')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="clients_can_register"
                            checked={data.clients_can_register}
                            onCheckedChange={(checked) => setData('clients_can_register', checked === true)}
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="clients_can_register" className="font-normal">
                                {t('Clients can register themselves')}
                            </Label>
                            <p className="text-muted-foreground text-sm">{t('Shows a registration form on the login screen.')}</p>
                        </div>
                    </div>
                    <InputError message={errors.clients_can_register} />

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="clients_auto_approve"
                            checked={data.clients_auto_approve}
                            disabled={!data.clients_can_register}
                            onCheckedChange={(checked) => setData('clients_auto_approve', checked === true)}
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="clients_auto_approve" className="font-normal">
                                {t('Auto approve new accounts')}
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                {t('When off, self-registered clients wait in the account requests queue until approved.')}
                            </p>
                        </div>
                    </div>
                    <InputError message={errors.clients_auto_approve} />

                    <div className="grid gap-2">
                        <Label htmlFor="clients_auto_group">{t('Add new clients to this group')}</Label>
                        <Select
                            value={data.clients_auto_group}
                            onValueChange={(value) => setData('clients_auto_group', value)}
                            disabled={!data.clients_can_register}
                        >
                            <SelectTrigger id="clients_auto_group" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="0">{t('None')}</SelectItem>
                                {groups.map((group) => (
                                    <SelectItem key={group.id} value={String(group.id)}>
                                        {group.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-sm">{t('Self-registered clients join this group automatically.')}</p>
                        <InputError message={errors.clients_auto_group} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="clients_can_select_group">{t('Groups clients can request to join at registration')}</Label>
                        <Select
                            value={data.clients_can_select_group}
                            onValueChange={(value) => setData('clients_can_select_group', value)}
                            disabled={!data.clients_can_register}
                        >
                            <SelectTrigger id="clients_can_select_group" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{t('None')}</SelectItem>
                                <SelectItem value="public">{t('Public groups')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-sm">
                            {t('Requested memberships wait in the membership requests queue for approval.')}
                        </p>
                        <InputError message={errors.clients_can_select_group} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="clients_membership_deny_cooldown_days">
                            {t('Days before a denied membership request can be made again')}
                        </Label>
                        <Input
                            id="clients_membership_deny_cooldown_days"
                            type="number"
                            min={0}
                            max={365}
                            className="w-32"
                            value={data.clients_membership_deny_cooldown_days}
                            onChange={(e) => setData('clients_membership_deny_cooldown_days', e.target.value)}
                        />
                        <p className="text-muted-foreground text-sm">{t('Set to 0 to allow requesting again immediately.')}</p>
                        <InputError message={errors.clients_membership_deny_cooldown_days} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="client_invitation_expiry_hours">{t('Invitation links expire after (hours)')}</Label>
                        <Input
                            id="client_invitation_expiry_hours"
                            type="number"
                            min={1}
                            max={720}
                            className="w-32"
                            value={data.client_invitation_expiry_hours}
                            onChange={(e) => setData('client_invitation_expiry_hours', e.target.value)}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t('How long a staff-sent invitation stays valid before the invited address has to ask for a new one.')}
                        </p>
                        <InputError message={errors.client_invitation_expiry_hours} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="default_client_storage_quota_mb">{t('Default storage quota (MB)')}</Label>
                        <Input
                            id="default_client_storage_quota_mb"
                            type="number"
                            min={0}
                            className="w-32"
                            value={data.default_client_storage_quota_mb}
                            onChange={(e) => setData('default_client_storage_quota_mb', e.target.value)}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Applied to any client without their own custom quota, including self-registered ones. Set to 0 for no default (unlimited unless a client has their own limit).',
                            )}
                        </p>
                        <InputError message={errors.default_client_storage_quota_mb} />
                    </div>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="clients_can_preview_files"
                            checked={data.clients_can_preview_files}
                            onCheckedChange={(checked) => setData('clients_can_preview_files', checked === true)}
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="clients_can_preview_files" className="font-normal">
                                {t('Clients can preview files')}
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Lets clients open an image, video, audio file or PDF in the portal instead of only downloading it. Turn it off to make downloading the only way to see a file. Staff can always preview.',
                                )}
                            </p>
                        </div>
                    </div>
                    <InputError message={errors.clients_can_preview_files} />

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="clients_home_folders"
                            checked={data.clients_home_folders}
                            onCheckedChange={(checked) => setData('clients_home_folders', checked === true)}
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="clients_home_folders" className="font-normal">
                                {t('Give each client a folder of their own')}
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'New clients get a folder named after them, and it acts as their root: what they upload and any folder they create goes inside it. In the file library you see one folder per client instead of everything at the top level. Clients still see anything you share with them, wherever it lives.',
                                )}
                            </p>
                        </div>
                    </div>
                    <InputError message={errors.clients_home_folders} />

                    {clients_home_folders && (
                        <div className="border-border bg-muted/30 rounded-lg border p-4">
                            <p className="text-sm font-medium">{t('Clients created before you turned this on')}</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {clients_without_home === 0
                                    ? t('Every client already has a folder.')
                                    : t(
                                          ':count of your existing clients have no folder yet. Creating them does not move any file — each client keeps what they already have, and new uploads go into the new folder.',
                                          { count: String(clients_without_home) },
                                      )}
                            </p>
                            {clients_without_home > 0 && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    className="mt-3"
                                    disabled={backfill.processing}
                                    onClick={() =>
                                        backfill.post(route('system-settings.clients.home-folders'), { preserveScroll: true })
                                    }
                                >
                                    {backfill.processing
                                        ? t('Creating folders…')
                                        : t('Create folders for these :count clients', { count: String(clients_without_home) })}
                                </Button>
                            )}
                        </div>
                    )}

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}

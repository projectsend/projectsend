import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SavedIndicator } from '@/components/save-button';
import { TestResultAlert } from '@/components/test-result-alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCapability } from '@/hooks/use-capability';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface MailProviderPreset {
    value: string;
    label: string;
    host: string | null;
    port: number | null;
}

interface MailProviderProps {
    provider: string;
    host: string;
    port: number | null;
    username: string;
    has_password: boolean;
    encryption: string;
    from_address: string;
    from_name: string;
}

interface EmailSettingsProps {
    email_notifications_enabled: boolean;
    admin_notification_emails: string[];
    mail_provider: MailProviderProps;
    mail_provider_presets: MailProviderPreset[];
    test_result: { ok: boolean; message: string } | null;
}

type Tab = 'general' | 'smtp' | 'test';

const FORM_ID = 'email-settings-form';

export default function EmailSettings({
    email_notifications_enabled,
    admin_notification_emails,
    mail_provider,
    mail_provider_presets,
    test_result,
}: EmailSettingsProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;
    const canConfigureTransport = useCapability('email.transport.configure');
    const tabs: Tab[] = canConfigureTransport ? ['general', 'smtp', 'test'] : ['general'];
    const [tab, setTab] = useState<Tab>('general');
    const [sendingTest, setSendingTest] = useState(false);
    const [testRecipient, setTestRecipient] = useState(auth.user.email);
    const [newRecipient, setNewRecipient] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Email'), href: '/system/settings/email' },
    ];

    const { data, setData, patch, processing, recentlySuccessful, errors } = useForm({
        email_notifications_enabled: email_notifications_enabled,
        admin_notification_emails: admin_notification_emails,
        provider: mail_provider.provider,
        host: mail_provider.host,
        port: mail_provider.port ?? 587,
        username: mail_provider.username,
        password: '',
        encryption: mail_provider.encryption,
        from_address: mail_provider.from_address,
        from_name: mail_provider.from_name,
    });

    const addRecipient = () => {
        const email = newRecipient.trim();
        if (email === '' || data.admin_notification_emails.includes(email)) return;
        setData('admin_notification_emails', [...data.admin_notification_emails, email]);
        setNewRecipient('');
    };

    const removeRecipient = (email: string) => {
        setData(
            'admin_notification_emails',
            data.admin_notification_emails.filter((existing) => existing !== email),
        );
    };

    const selectPreset = (value: string) => {
        const preset = mail_provider_presets.find((p) => p.value === value);
        setData((current) => ({
            ...current,
            provider: value,
            host: preset?.host ?? current.host,
            port: preset?.port ?? current.port,
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('system-settings.email.update'), {
            preserveScroll: true,
            onSuccess: () => setData('password', ''),
        });
    };

    const sendTest: FormEventHandler = (e) => {
        e.preventDefault();
        setSendingTest(true);
        router.post(
            route('system-settings.email.test'),
            { recipient: testRecipient },
            { preserveScroll: true, onFinish: () => setSendingTest(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Email settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Email settings')} description={t('Transactional email settings for this installation')} />

                {tabs.length > 1 && (
                    <nav className="mt-6 flex gap-1 border-b">
                        {tabs.map((tabKey) => (
                            <button
                                key={tabKey}
                                onClick={() => setTab(tabKey)}
                                className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                            >
                                {tabKey === 'general' ? t('General') : tabKey === 'smtp' ? t('SMTP configuration') : t('Test')}
                            </button>
                        ))}
                    </nav>
                )}

                <form id={FORM_ID} onSubmit={submit} className="mt-6 max-w-xl">
                    {tab === 'general' && (
                        <div className="space-y-6">
                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id="email_notifications_enabled"
                                    checked={data.email_notifications_enabled}
                                    onCheckedChange={(checked) => setData('email_notifications_enabled', checked === true)}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="email_notifications_enabled" className="font-normal">
                                        {t('Send email notifications')}
                                    </Label>
                                    <p className="text-muted-foreground text-sm">
                                        {t('Turns all transactional email for this installation on or off.')}
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="admin_notification_emails">{t('Admin notification recipients')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('New client registrations and client uploads are sent here — at least one address is required.')}
                                </p>

                                <div className="flex gap-2">
                                    <Input
                                        id="admin_notification_emails"
                                        type="email"
                                        placeholder={t('Email address')}
                                        value={newRecipient}
                                        onChange={(e) => setNewRecipient(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addRecipient();
                                            }
                                        }}
                                        className="w-72"
                                    />
                                    <Button type="button" onClick={addRecipient} disabled={newRecipient.trim() === ''}>
                                        {t('Add')}
                                    </Button>
                                </div>

                                <div className="rounded-lg border">
                                    {data.admin_notification_emails.length === 0 && (
                                        <p className="text-muted-foreground px-4 py-6 text-center text-sm">{t('No recipients added yet.')}</p>
                                    )}
                                    {data.admin_notification_emails.map((email) => (
                                        <div key={email} className="flex items-center justify-between border-b px-4 py-2 last:border-0">
                                            <p className="text-sm font-medium">{email}</p>
                                            <Button type="button" variant="ghost" size="sm" onClick={() => removeRecipient(email)}>
                                                <X className="size-4" />
                                                <span className="sr-only">{t('Remove')}</span>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.admin_notification_emails} />
                            </div>

                            {!canConfigureTransport && (
                                <div className="grid gap-2">
                                    <Label>{t('Sender identity')}</Label>
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'Outgoing email is sent through a centrally managed relay for cloud accounts. You can still choose the From address and name shown to recipients.',
                                        )}
                                    </p>

                                    <div className="flex gap-4">
                                        <div className="grid flex-1 gap-2">
                                            <Label htmlFor="mail_from_address">{t('From address')}</Label>
                                            <Input
                                                id="mail_from_address"
                                                type="email"
                                                value={data.from_address}
                                                onChange={(e) => setData('from_address', e.target.value)}
                                            />
                                            <InputError message={errors.from_address} />
                                        </div>
                                        <div className="grid flex-1 gap-2">
                                            <Label htmlFor="mail_from_name">{t('From name')}</Label>
                                            <Input
                                                id="mail_from_name"
                                                value={data.from_name}
                                                onChange={(e) => setData('from_name', e.target.value)}
                                            />
                                            <InputError message={errors.from_name} />
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {tab === 'smtp' && canConfigureTransport && (
                        <div className="space-y-6">
                            <p className="text-muted-foreground text-sm">{t('Where outgoing email is actually sent from.')}</p>

                            <div className="grid gap-2">
                                <Label htmlFor="mail_provider">{t('Provider')}</Label>
                                <Select value={data.provider} onValueChange={selectPreset}>
                                    <SelectTrigger id="mail_provider" className="w-64">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {mail_provider_presets.map((preset) => (
                                            <SelectItem key={preset.value} value={preset.value}>
                                                {preset.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex gap-4">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="mail_host">{t('Host')}</Label>
                                    <Input id="mail_host" value={data.host} onChange={(e) => setData('host', e.target.value)} />
                                    <InputError message={errors.host} />
                                </div>
                                <div className="grid w-32 gap-2">
                                    <Label htmlFor="mail_port">{t('Port')}</Label>
                                    <Input id="mail_port" type="number" value={data.port} onChange={(e) => setData('port', Number(e.target.value))} />
                                    <InputError message={errors.port} />
                                </div>
                            </div>

                            <div className="flex gap-4">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="mail_username">{t('Username')}</Label>
                                    <Input id="mail_username" value={data.username} onChange={(e) => setData('username', e.target.value)} />
                                    <InputError message={errors.username} />
                                </div>
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="mail_password">{t('Password')}</Label>
                                    <Input
                                        id="mail_password"
                                        type="password"
                                        placeholder={mail_provider.has_password ? t('Unchanged') : ''}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                    />
                                    <InputError message={errors.password} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="mail_encryption">{t('Encryption')}</Label>
                                <Select value={data.encryption} onValueChange={(v) => setData('encryption', v)}>
                                    <SelectTrigger id="mail_encryption" className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="tls">{t('TLS')}</SelectItem>
                                        <SelectItem value="ssl">{t('SSL')}</SelectItem>
                                        <SelectItem value="none">{t('None')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.encryption} />
                            </div>

                            <div className="flex gap-4">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="mail_from_address">{t('From address')}</Label>
                                    <Input
                                        id="mail_from_address"
                                        type="email"
                                        value={data.from_address}
                                        onChange={(e) => setData('from_address', e.target.value)}
                                    />
                                    <InputError message={errors.from_address} />
                                </div>
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="mail_from_name">{t('From name')}</Label>
                                    <Input id="mail_from_name" value={data.from_name} onChange={(e) => setData('from_name', e.target.value)} />
                                    <InputError message={errors.from_name} />
                                </div>
                            </div>
                        </div>
                    )}
                </form>

                {tab === 'test' && canConfigureTransport && (
                    <div className="mt-6 max-w-xl space-y-6">
                        <p className="text-muted-foreground text-sm">
                            {t('Sends a real email right now, bypassing the queue, so you can verify the settings above actually work.')}
                        </p>

                        <form onSubmit={sendTest} className="flex items-end gap-2">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="test_recipient">{t('Recipient')}</Label>
                                <Input id="test_recipient" type="email" value={testRecipient} onChange={(e) => setTestRecipient(e.target.value)} />
                            </div>
                            <Button type="submit" disabled={sendingTest || testRecipient.trim() === ''}>
                                {t('Send test email')}
                            </Button>
                        </form>

                        {test_result && <TestResultAlert ok={test_result.ok}>{test_result.message}</TestResultAlert>}
                    </div>
                )}

                <div className="mt-6 flex max-w-xl items-center gap-4">
                    <Button type="submit" form={FORM_ID} disabled={processing}>
                        {t('Save')}
                    </Button>

                    <SavedIndicator recentlySuccessful={recentlySuccessful} />
                </div>
            </div>
        </AppLayout>
    );
}

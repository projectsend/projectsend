import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { TestResultAlert } from '@/components/test-result-alert';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface Encryption {
    value: string;
    label: string;
    default_port: number;
}

interface LdapSettings {
    active: boolean;
    host: string | null;
    port: number;
    encryption: string;
    ca_cert_path: string | null;
    bind_dn: string | null;
    has_bind_password: boolean;
    base_dn: string | null;
    user_filter: string | null;
    email_attribute: string;
    name_attribute: string;
    auto_provision: boolean;
    auto_approve: boolean;
}

interface LdapPageProps {
    ldap: LdapSettings;
    encryptions: Encryption[];
    extension_available: boolean;
    clients_auto_approve: boolean;
    test_result: { ok: boolean; stage: string; message: string; dn: string | null } | null;
}

type Tab = 'connection' | 'directory' | 'test';

export default function LdapSettingsPage({ ldap, encryptions, extension_available, clients_auto_approve, test_result }: LdapPageProps) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>('connection');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('LDAP'), href: '/system/settings/ldap' },
    ];

    const form = useForm({
        active: ldap.active,
        host: ldap.host ?? '',
        port: ldap.port,
        encryption: ldap.encryption,
        ca_cert_path: ldap.ca_cert_path ?? '',
        bind_dn: ldap.bind_dn ?? '',
        bind_password: '',
        base_dn: ldap.base_dn ?? '',
        user_filter: ldap.user_filter ?? '',
        email_attribute: ldap.email_attribute,
        name_attribute: ldap.name_attribute,
        auto_provision: ldap.auto_provision,
        auto_approve: ldap.auto_approve,
    });

    const testForm = useForm({ email: '', password: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('system-settings.ldap.update'), { preserveScroll: true, preserveState: true });
    };

    const runTest = (withCredentials: boolean) => {
        testForm.transform((data) => (withCredentials ? data : { email: '', password: '' }));
        testForm.post(route('system-settings.ldap.test'), { preserveScroll: true, preserveState: true });
    };

    // Selecting LDAPS should move the port with it — 636 is not something
    // an administrator should have to remember.
    const changeEncryption = (value: string) => {
        const chosen = encryptions.find((e) => e.value === value);
        form.setData((current) => ({
            ...current,
            encryption: value,
            port: chosen ? chosen.default_port : current.port,
        }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('LDAP settings')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('LDAP settings')}
                    description={t('Let clients sign in with their directory account. Staff always authenticate locally.')}
                />

                {!extension_available && (
                    <Alert variant="destructive" className="mb-6 max-w-2xl">
                        <AlertDescription>
                            {t('The PHP LDAP extension is not installed on this server, so these settings cannot take effect.')}
                        </AlertDescription>
                    </Alert>
                )}

                <nav className="mb-6 flex gap-1 border-b">
                    {(['connection', 'directory', 'test'] as Tab[]).map((key) => (
                        <button
                            type="button"
                            key={key}
                            onClick={() => setTab(key)}
                            className={`border-b-2 px-3 py-2 text-sm ${tab === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {key === 'connection' ? t('Connection') : key === 'directory' ? t('Directory') : t('Test')}
                        </button>
                    ))}
                </nav>

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <section className={`space-y-6 ${tab === 'connection' ? '' : 'hidden'}`}>
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="active"
                                checked={form.data.active}
                                disabled={!extension_available}
                                onCheckedChange={(checked) => form.setData('active', checked === true)}
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="active">{t('Allow clients to sign in with a directory account')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'When a client’s password is not recognised locally, it is checked against the directory. Staff accounts always authenticate locally and are never sent to it.',
                                    )}
                                </p>
                            </div>
                        </div>
                        <InputError message={form.errors.active} />

                        <div className="grid gap-2">
                            <Label htmlFor="host">{t('Server')}</Label>
                            <Input
                                id="host"
                                value={form.data.host}
                                placeholder="ldap.example.com"
                                onChange={(e) => form.setData('host', e.target.value)}
                            />
                            <InputError message={form.errors.host} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="encryption">{t('Encryption')}</Label>
                            <Select value={form.data.encryption} onValueChange={changeEncryption}>
                                <SelectTrigger id="encryption" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {encryptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {t(option.label)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.data.encryption === 'none' && (
                                <p className="text-destructive text-sm">
                                    {t('Unencrypted: the service account password and every client password cross the network in clear text.')}
                                </p>
                            )}
                            <InputError message={form.errors.encryption} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="port">{t('Port')}</Label>
                            <Input
                                id="port"
                                type="number"
                                className="max-w-32"
                                value={form.data.port}
                                onChange={(e) => form.setData('port', Number(e.target.value))}
                            />
                            <InputError message={form.errors.port} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ca_cert_path">{t('CA certificate path (optional)')}</Label>
                            <Input id="ca_cert_path" value={form.data.ca_cert_path} onChange={(e) => form.setData('ca_cert_path', e.target.value)} />
                            <p className="text-muted-foreground text-sm">
                                {t('For a directory presenting a certificate from your own certificate authority.')}
                            </p>
                            <InputError message={form.errors.ca_cert_path} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="bind_dn">{t('Service account DN')}</Label>
                            <Input
                                id="bind_dn"
                                value={form.data.bind_dn}
                                placeholder="cn=projectsend,ou=services,dc=example,dc=com"
                                onChange={(e) => form.setData('bind_dn', e.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">{t('Leave blank to search anonymously.')}</p>
                            <InputError message={form.errors.bind_dn} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="bind_password">{t('Service account password')}</Label>
                            <Input
                                id="bind_password"
                                type="password"
                                value={form.data.bind_password}
                                placeholder={ldap.has_bind_password ? t('Unchanged') : ''}
                                onChange={(e) => form.setData('bind_password', e.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">
                                {ldap.has_bind_password
                                    ? t('Stored encrypted. Leave blank to keep the current one.')
                                    : t('Stored encrypted, and never shown again.')}
                            </p>
                            <InputError message={form.errors.bind_password} />
                        </div>
                    </section>

                    <section className={`space-y-6 ${tab === 'directory' ? '' : 'hidden'}`}>
                        <div className="grid gap-2">
                            <Label htmlFor="base_dn">{t('Base DN')}</Label>
                            <Input
                                id="base_dn"
                                value={form.data.base_dn}
                                placeholder="ou=people,dc=example,dc=com"
                                onChange={(e) => form.setData('base_dn', e.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">{t('Where in the tree to look for people.')}</p>
                            <InputError message={form.errors.base_dn} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email_attribute">{t('Email attribute')}</Label>
                            <Input
                                id="email_attribute"
                                className="max-w-64"
                                value={form.data.email_attribute}
                                onChange={(e) => form.setData('email_attribute', e.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">
                                {t('The attribute holding the address people sign in with — usually mail.')}
                            </p>
                            <InputError message={form.errors.email_attribute} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name_attribute">{t('Name attribute')}</Label>
                            <Input
                                id="name_attribute"
                                className="max-w-64"
                                value={form.data.name_attribute}
                                onChange={(e) => form.setData('name_attribute', e.target.value)}
                            />
                            <InputError message={form.errors.name_attribute} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user_filter">{t('Additional filter (optional)')}</Label>
                            <Input
                                id="user_filter"
                                value={form.data.user_filter}
                                placeholder="(memberOf=cn=clients,ou=groups,dc=example,dc=com)"
                                onChange={(e) => form.setData('user_filter', e.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">{t('Combined with the address match, to narrow who may sign in.')}</p>
                            <InputError message={form.errors.user_filter} />
                        </div>

                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="auto_provision"
                                checked={form.data.auto_provision}
                                onCheckedChange={(checked) => form.setData('auto_provision', checked === true)}
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="auto_provision">{t('Create an account on first sign-in')}</Label>
                                {/* Reads the settings that actually decide this, so the
                                    interaction is visible here rather than documented
                                    somewhere else. */}
                                <p className="text-muted-foreground text-sm">
                                    {form.data.auto_provision
                                        ? form.data.auto_approve
                                            ? t('Someone in the directory with no account here gets a client account and is signed in immediately.')
                                            : t(
                                                  'Someone in the directory with no account here gets a client account that waits in Account requests for approval.',
                                              )
                                        : t('Only people who already have an account here can sign in.')}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {t('Directory accounts are always created as clients, never as staff.')}
                                </p>
                            </div>
                        </div>
                        <InputError message={form.errors.auto_provision} />

                        {/* Only meaningful when something is being created, so it
                            follows the checkbox that decides that rather than
                            standing on its own. */}
                        {form.data.auto_provision && (
                            <div className="ml-7 flex items-start gap-3">
                                <Checkbox
                                    id="auto_approve"
                                    checked={form.data.auto_approve}
                                    onCheckedChange={(checked) => form.setData('auto_approve', checked === true)}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="auto_approve">{t('Approve these accounts automatically')}</Label>
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'Your directory has already established who they are, so there is nobody to vet. Leave this off to review each one in Account requests first.',
                                        )}
                                    </p>
                                    {/* The neighbouring setting people will assume this
                                        follows. Worth saying plainly when they differ. */}
                                    {form.data.auto_approve !== clients_auto_approve && (
                                        <p className="text-muted-foreground text-sm">
                                            {clients_auto_approve
                                                ? t(
                                                      'Clients who register themselves are approved automatically — this setting is separate, and does not follow it.',
                                                  )
                                                : t(
                                                      'Clients who register themselves still wait for approval — this setting is separate, and does not change that.',
                                                  )}
                                        </p>
                                    )}
                                    <InputError message={form.errors.auto_approve} />
                                </div>
                            </div>
                        )}
                    </section>

                    <div className={tab === 'test' ? '' : 'hidden'}>
                        <div className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                {t('Saves are not required to test — but the test uses the settings as last saved.')}
                            </p>

                            <div className="flex flex-wrap items-end gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="test-email">{t('Email address (optional)')}</Label>
                                    <Input
                                        id="test-email"
                                        className="w-72"
                                        value={testForm.data.email}
                                        onChange={(e) => testForm.setData('email', e.target.value)}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="test-password">{t('Password (optional)')}</Label>
                                    <Input
                                        id="test-password"
                                        type="password"
                                        className="w-56"
                                        value={testForm.data.password}
                                        onChange={(e) => testForm.setData('password', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <Button type="button" variant="outline" onClick={() => runTest(false)} disabled={testForm.processing}>
                                    {t('Test connection')}
                                </Button>
                                <Button type="button" variant="outline" onClick={() => runTest(true)} disabled={testForm.processing}>
                                    {t('Test a sign-in')}
                                </Button>
                            </div>

                            {test_result && (
                                <TestResultAlert ok={test_result.ok}>
                                    {`${t(test_result.stage)}\n${test_result.message}${test_result.dn ? `\n${test_result.dn}` : ''}`}
                                </TestResultAlert>
                            )}
                        </div>
                    </div>

                    <div className={`flex items-center gap-4 ${tab === 'test' ? 'hidden' : ''}`}>
                        <SaveButton processing={form.processing} recentlySuccessful={form.recentlySuccessful} />
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

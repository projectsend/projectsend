import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Loader2, Search } from 'lucide-react';
import { FormEventHandler, KeyboardEvent, useEffect, useMemo, useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ListPager } from '@/components/pagination';
import { SaveButton } from '@/components/save-button';
import { TableShell } from '@/components/table-shell';
import { TestResultAlert } from '@/components/test-result-alert';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
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
    username_attribute: string | null;
    auto_provision: boolean;
    auto_approve: boolean;
    sync_daily: boolean;
    sync_deactivates_missing: boolean;
    sync_restores_deleted: boolean;
}

/** One directory sync, or a preview of one — see LdapSync. */
interface SyncReport {
    /** stopped: the safety limit held an unreviewed run back; cancelled: somebody pressed Stop. */
    status: 'running' | 'finished' | 'failed' | 'stopped' | 'cancelled';
    dry_run: boolean;
    finished_at: number | null;
    found: number;
    created: number;
    updated: number;
    unchanged: number;
    restored: number;
    skipped: Record<string, number>;
    missing: number;
    deactivated: number;
    errors: number;
    error: string | null;
    /** Only filled for a preview: everybody behind the counts. */
    people: SyncPerson[];
    /** Only filled for a real run: who it could not handle (at most 20). */
    failures: { email: string; error: string }[];
    /** A preview's plan, which Sync now carries out. */
    plan: string | null;
    /** Who a real run deactivated and has not been reactivated since. */
    deactivated_ids?: number[];
    reactivated?: number;
}

type SyncAction = 'create' | 'update' | 'restore' | 'deactivate' | 'keep' | 'skip' | 'error' | 'unchanged';

interface SyncPerson {
    name: string;
    email: string;
    action: SyncAction;
    reason?: string;
    previous_name?: string;
    moved?: boolean;
    error?: string;
    /** A deleted client an administrator deleted: it can be ticked to restore. */
    restorable?: boolean;
}

/** Often enough to follow a running sync, rarely enough not to matter. */
const SYNC_POLL_MS = 3000;

/** Matches LdapSettingsController::PREVIEW_VALID_SECONDS. */
const PREVIEW_VALID_MS = 10 * 60 * 1000;

/** Rows per page of the preview table. */
const PREVIEW_PAGE = 10;

/** Above this many people, a search box joins the filters. */
const SEARCH_FROM = 10;

/** A preview that would deactivate more than this share of directory clients warns first. Matches LdapSync::DEACTIVATE_LIMIT_SHARE. */
const DEACTIVATE_WARN_SHARE = 0.2;

/** Move focus once the page has re-rendered, so a replaced control does not drop it on the body. */
const focusLater = (id: string) => window.requestAnimationFrame(() => document.getElementById(id)?.focus());

const TABS = ['connection', 'directory', 'sync', 'test'] as const;

/**
 * Arrow-key movement for a row of role="tab" buttons, as the WAI-ARIA tabs
 * pattern expects: Left/Right step, Home/End jump.
 */
function moveBetweenTabs<T extends string>(event: KeyboardEvent, keys: readonly T[], current: T, select: (key: T) => void) {
    const index = keys.indexOf(current);
    const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: keys.length - 1 }[event.key];

    if (next === undefined) return;

    event.preventDefault();
    const key = keys[(next + keys.length) % keys.length];
    select(key);
    document.getElementById(`${event.currentTarget.id.replace(/-[^-]+$/, '')}-${key}`)?.focus();
}

interface LdapPageProps {
    ldap: LdapSettings;
    encryptions: Encryption[];
    extension_available: boolean;
    clients_auto_approve: boolean;
    test_result: { ok: boolean; stage: string; message: string; dn: string | null } | null;
    sync: SyncReport | null;
    sync_preview: SyncReport | null;
}

type Tab = 'connection' | 'directory' | 'sync' | 'test';

export default function LdapSettingsPage({
    ldap,
    encryptions,
    extension_available,
    clients_auto_approve,
    test_result,
    sync,
    sync_preview,
}: LdapPageProps) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>(sync_preview || sync?.status === 'running' ? 'sync' : 'connection');
    const syncRunning = sync?.status === 'running';
    // Sync posts through the router rather than the settings form, so its
    // refusal arrives with the page's shared errors.
    const syncError = (usePage().props.errors as Record<string, string | undefined>).sync;

    // Follow a background sync until it ends.
    useEffect(() => {
        if (!syncRunning) return;

        const id = window.setInterval(() => router.reload({ only: ['sync'] }), SYNC_POLL_MS);

        return () => window.clearInterval(id);
    }, [syncRunning]);

    // A preview reads the whole directory in the request, which can take a
    // moment; the button says so rather than inviting a second click.
    const [previewing, setPreviewing] = useState(false);

    const runSync = (dryRun: boolean, restore: string[] = []) =>
        router.post(route('system-settings.ldap.sync'), dryRun ? { dry_run: true } : { dry_run: false, restore }, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => dryRun && setPreviewing(true),
            onFinish: () => setPreviewing(false),
            onSuccess: () => focusLater(dryRun ? 'sync-preview-heading' : 'sync-status'),
        });

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
        username_attribute: ldap.username_attribute ?? '',
        auto_provision: ldap.auto_provision,
        auto_approve: ldap.auto_approve,
        sync_daily: ldap.sync_daily,
        sync_deactivates_missing: ldap.sync_deactivates_missing,
        sync_restores_deleted: ldap.sync_restores_deleted,
    });

    const testForm = useForm({ email: '', password: '' });

    // Why Preview and Sync now are unavailable, said next to them and read
    // out with them; the buttons stay focusable so the reason can be found.
    const syncBlockedReason = form.isDirty
        ? t('Save your changes first. Preview and sync use the saved settings.')
        : syncRunning
          ? t('A sync is running. Preview again once it has finished.')
          : null;

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

                <div role="tablist" aria-label={t('LDAP settings')} className="mb-6 flex gap-1 border-b">
                    {TABS.map((key) => (
                        <button
                            type="button"
                            role="tab"
                            key={key}
                            id={`ldap-tab-${key}`}
                            aria-selected={tab === key}
                            aria-controls={`ldap-panel-${key}`}
                            tabIndex={tab === key ? 0 : -1}
                            onClick={() => setTab(key)}
                            onKeyDown={(event) => moveBetweenTabs(event, TABS, tab, setTab)}
                            className={`focus-visible:ring-ring -mb-px border-b-2 px-3 py-2 text-sm outline-none focus-visible:ring-2 ${tab === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground hover:text-foreground border-transparent'}`}
                        >
                            {{ connection: t('Connection'), directory: t('Directory'), sync: t('Sync'), test: t('Test') }[key]}
                        </button>
                    ))}
                </div>

                <form onSubmit={submit} className={`${tab === 'sync' ? 'max-w-4xl' : 'max-w-2xl'} space-y-6`}>
                    <section
                        role="tabpanel"
                        id="ldap-panel-connection"
                        aria-labelledby="ldap-tab-connection"
                        className={`space-y-6 ${tab === 'connection' ? '' : 'hidden'}`}
                    >
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

                    <section
                        role="tabpanel"
                        id="ldap-panel-directory"
                        aria-labelledby="ldap-tab-directory"
                        className={`space-y-6 ${tab === 'directory' ? '' : 'hidden'}`}
                    >
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
                            <Label htmlFor="username_attribute">{t('Username attribute (optional)')}</Label>
                            <Input
                                id="username_attribute"
                                className="max-w-64"
                                value={form.data.username_attribute}
                                placeholder="uid"
                                onChange={(e) => form.setData('username_attribute', e.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Lets people sign in with their directory username as well as their address: uid, cn or sAMAccountName, for example. Leave it empty to sign in by address only.',
                                )}
                            </p>
                            <InputError message={form.errors.username_attribute} />
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

                    <section
                        role="tabpanel"
                        id="ldap-panel-sync"
                        aria-labelledby="ldap-tab-sync"
                        className={`space-y-8 ${tab === 'sync' ? '' : 'hidden'}`}
                    >
                        <div className="space-y-5">
                            <p className="text-muted-foreground max-w-2xl text-sm">
                                {t(
                                    'Brings client accounts in line with the directory: creates accounts for people who have none (when accounts are created on first sign-in), and updates the names of directory accounts. Staff and local accounts are never changed.',
                                )}
                            </p>

                            {(
                                [
                                    [
                                        'sync_daily',
                                        t('Sync every day'),
                                        t('Runs every day at midnight, server time. You can also preview and sync below at any time.'),
                                    ],
                                    [
                                        'sync_deactivates_missing',
                                        t('Deactivate client accounts that leave the directory'),
                                        t(
                                            'Only accounts that came from the directory. Left off, they are kept and counted. A sync that finds nobody in the directory never deactivates anyone, and the daily run stops without changing anything if it would deactivate more than a fifth of them.',
                                        ),
                                    ],
                                    [
                                        'sync_restores_deleted',
                                        t('Restore deleted client accounts that are in the directory'),
                                        t(
                                            'Undoes an administrator’s deletion while the account is still waiting to be erased, and brings it back as it was. Accounts people deleted themselves are never restored.',
                                        ),
                                    ],
                                ] as const
                            ).map(([field, label, hint]) => (
                                <div key={field} className="flex items-start gap-3">
                                    <Checkbox
                                        id={field}
                                        aria-describedby={`${field}-hint`}
                                        checked={form.data[field]}
                                        onCheckedChange={(checked) => form.setData(field, checked === true)}
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor={field}>{label}</Label>
                                        <p id={`${field}-hint`} className="text-muted-foreground text-sm">
                                            {hint}
                                        </p>
                                    </div>
                                </div>
                            ))}

                            <SaveButton processing={form.processing} recentlySuccessful={form.recentlySuccessful} />
                        </div>

                        <div className="space-y-4 border-t pt-6">
                            <SyncStatus sync={sync} />

                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                {/* aria-disabled rather than disabled: a disabled button
                                    drops focus mid-click and is skipped by Tab, taking
                                    the reason with it. A fixed width keeps the hint
                                    beside it from sliding as the label changes. */}
                                <Button
                                    type="button"
                                    variant={sync_preview ? 'outline' : 'default'}
                                    className="min-w-36 aria-disabled:pointer-events-none aria-disabled:opacity-50"
                                    aria-disabled={syncBlockedReason !== null || previewing}
                                    aria-describedby="sync-preview-hint"
                                    aria-busy={previewing}
                                    onClick={() => syncBlockedReason === null && !previewing && runSync(true)}
                                >
                                    {previewing && <Loader2 className="size-4 animate-spin motion-reduce:animate-none" aria-hidden />}
                                    {previewing ? t('Previewing…') : sync_preview ? t('Preview again') : t('Preview sync')}
                                </Button>
                                <p
                                    id="sync-preview-hint"
                                    className={`text-sm ${syncBlockedReason !== null ? 'text-amber-800 dark:text-amber-300' : 'text-muted-foreground'}`}
                                >
                                    {syncBlockedReason ??
                                        (previewing
                                            ? t('Reading the directory…')
                                            : t('Shows who a sync would create, update, restore or deactivate. Changes nothing.'))}
                                </p>
                            </div>

                            {/* Outside the preview: a refused sync comes back without
                                one, and the reason must still be seen and heard. */}
                            <p role="alert" className={`text-sm empty:hidden ${DANGER_TEXT}`}>
                                {syncError}
                            </p>

                            {sync_preview && (
                                <SyncPreview
                                    key={sync_preview.finished_at ?? 0}
                                    report={sync_preview}
                                    autoApprove={ldap.auto_approve}
                                    blockedReason={syncBlockedReason ?? (previewing ? t('Previewing…') : null)}
                                    onPreview={() => runSync(true)}
                                    onSync={(restore) => runSync(false, restore)}
                                    onOpenTab={setTab}
                                />
                            )}
                        </div>
                    </section>

                    <div role="tabpanel" id="ldap-panel-test" aria-labelledby="ldap-tab-test" className={tab === 'test' ? '' : 'hidden'}>
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

                    <div className={`flex items-center gap-4 ${tab === 'test' || tab === 'sync' ? 'hidden' : ''}`}>
                        <SaveButton processing={form.processing} recentlySuccessful={form.recentlySuccessful} />
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

/** Red text that keeps 4.5:1 on the page in both themes (text-destructive does not in light). */
const DANGER_TEXT = 'text-red-700 dark:text-red-400';

const FOCUS_RING = 'focus-visible:ring-ring rounded-sm outline-none focus-visible:ring-2';

/** The theme's destructive red is under 4.5:1 behind white text; this one is not. */
const DANGER_FILL = 'bg-red-700 text-white hover:bg-red-800 dark:bg-red-700 dark:hover:bg-red-800';

/** Entries skipped for any reason, so progress can reach the total. */
const skippedOf = (report: SyncReport) => Object.values(report.skipped).reduce((sum, count) => sum + count, 0);

/**
 * The last real sync, or the one running now. The live region is always
 * in the page, so a change of state is announced; it carries only the
 * state, not the counts that tick every few seconds.
 */
function SyncStatus({ sync }: { sync: SyncReport | null }) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const counts = sync && {
        date: sync.finished_at ? dateTime(new Date(sync.finished_at * 1000).toISOString()) : '',
        created: sync.created,
        updated: sync.updated,
        restored: sync.restored,
        deactivated: sync.deactivated,
    };
    const summary =
        counts && sync.status === 'finished'
            ? t('Last sync :date: :created created, :updated updated, :restored restored, :deactivated deactivated.', counts)
            : counts && sync.status === 'cancelled'
              ? t('The last sync was stopped :date, after :created created, :updated updated, :restored restored, :deactivated deactivated.', counts)
              : '';
    // Directory clients no longer listed and left alone, which the
    // deactivation option promises to count.
    const kept = sync?.status === 'finished' ? sync.missing - sync.deactivated : 0;
    const undoable = sync?.deactivated_ids?.length ?? 0;
    const [stopping, setStopping] = useState(false);

    // The live region announces each state once. A finished run says what it
    // did; a running one does not tick, or it would talk every few seconds.
    const state = !sync
        ? ''
        : sync.status === 'running'
          ? t('Syncing with the directory')
          : sync.status === 'failed'
            ? `${t('The last sync failed')}: ${sync.error ?? ''}`
            : sync.status === 'stopped'
              ? (sync.error ?? '')
              : summary;

    const stop = () =>
        router.post(
            route('system-settings.ldap.sync.cancel'),
            {},
            { preserveScroll: true, onStart: () => setStopping(true), onFinish: () => setStopping(false) },
        );
    const reactivate = () => router.post(route('system-settings.ldap.sync.reactivate'), {}, { preserveScroll: true });

    return (
        <div id="sync-status" tabIndex={-1} className="space-y-2 outline-none">
            <p role="status" aria-live="polite" className="sr-only">
                {state}
            </p>

            {sync?.status === 'running' && (
                <div className="bg-muted/40 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border px-4 py-3 text-sm">
                    <Loader2 className="size-4 shrink-0 animate-spin motion-reduce:animate-none" aria-hidden />
                    <span className="flex-1">
                        {sync.found === 0
                            ? t('Starting the sync…')
                            : t('Syncing with the directory: :done of :found handled so far.', {
                                  done: sync.created + sync.updated + sync.restored + sync.unchanged + sync.errors + skippedOf(sync),
                                  found: sync.found,
                              })}
                    </span>
                    {/* Not destructive: what the run already did stays done, and
                        it stops between two people rather than halfway through one. */}
                    <Button type="button" variant="outline" size="sm" onClick={stop} disabled={stopping}>
                        {t('Stop')}
                    </Button>
                </div>
            )}

            {sync?.status === 'failed' && (
                <div className="border-destructive/50 rounded-lg border px-4 py-3 text-sm">
                    <p className={`font-medium ${DANGER_TEXT}`}>{t('The last sync failed')}</p>
                    <p className="text-muted-foreground mt-1">{sync.error}</p>
                    <p className="text-muted-foreground mt-1">{t('Check the connection settings, then test them.')}</p>
                </div>
            )}

            {sync?.status === 'stopped' && (
                <div className="rounded-lg border border-amber-300 px-4 py-3 text-sm dark:border-amber-800">
                    <p className="font-medium text-amber-800 dark:text-amber-300">{t('The last sync was held back for review')}</p>
                    <p className="text-muted-foreground mt-1">{sync.error}</p>
                </div>
            )}

            {(sync?.status === 'finished' || sync?.status === 'cancelled') && (
                <div className="space-y-2 text-sm">
                    <p className="text-muted-foreground">
                        {summary} {kept > 0 && t('No longer in the directory and kept: :count.', { count: kept })}{' '}
                        {(sync.reactivated ?? 0) > 0 && t('Reactivated since: :count.', { count: sync.reactivated ?? 0 })}
                    </p>
                    {undoable > 0 && (
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <Button type="button" variant="outline" size="sm" onClick={reactivate}>
                                {t('Reactivate the :count it deactivated', { count: undoable })}
                            </Button>
                            <Link
                                href={route('clients.index', { status: 'inactive' })}
                                className={`text-foreground underline underline-offset-4 ${FOCUS_RING}`}
                            >
                                {t('Review deactivated clients')}
                            </Link>
                        </div>
                    )}
                    {sync.errors > 0 && (
                        <details className="text-sm">
                            <summary className={`cursor-pointer ${DANGER_TEXT} ${FOCUS_RING}`}>
                                {t(':count could not be synced.', { count: sync.errors })}
                            </summary>
                            <ul className="text-muted-foreground mt-1 list-inside list-disc">
                                {sync.failures.map((failure) => (
                                    <li key={failure.email}>
                                        {failure.email}: {failure.error}
                                    </li>
                                ))}
                            </ul>
                            {sync.errors > sync.failures.length && (
                                <p className="text-muted-foreground mt-1">
                                    {t('Only the first :shown are listed.', { shown: sync.failures.length })}
                                </p>
                            )}
                        </details>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * The filter tabs. Every action that changes something gets its own; the
 * rows nothing happens to share one, so the row stays short.
 */
type Filter = 'create' | 'update' | 'restore' | 'deactivate' | 'keep' | 'error' | 'other' | 'all';
const FILTERS: readonly Filter[] = ['create', 'update', 'restore', 'deactivate', 'keep', 'error', 'other', 'all'];
/** A deleted client that could come back, whether or not it is ticked to. */
const isRestorable = (person: SyncPerson) => person.action === 'restore' || person.restorable === true;
const filterOf = (person: SyncPerson): Filter =>
    isRestorable(person) ? 'restore' : person.action === 'skip' || person.action === 'unchanged' ? 'other' : person.action;

/** One label per action, shared by the filter tabs and the row badges. */
function useActionLabels() {
    const { t } = useTranslation();

    return {
        create: t('Create'),
        update: t('Update'),
        restore: t('Restore'),
        deactivate: t('Deactivate'),
        keep: t('Keep'),
        error: t('Error'),
        skip: t('Skipped'),
        unchanged: t('Unchanged'),
        other: t('Skipped or unchanged'),
        all: t('Everyone'),
    } satisfies Record<SyncAction | Filter, string>;
}

/**
 * Who a sync would touch, before it touches them: filter tabs carrying the
 * counts, the people behind them, and the only way to start a real sync —
 * a confirmation that quotes what this preview found.
 */
function SyncPreview({
    report,
    autoApprove,
    blockedReason,
    onPreview,
    onSync,
    onOpenTab,
}: {
    report: SyncReport;
    autoApprove: boolean;
    /** Why a sync cannot start right now, or null when it can. */
    blockedReason: string | null;
    onPreview: () => void;
    /** Start the sync, restoring the deleted clients at these addresses. */
    onSync: (restore: string[]) => void;
    onOpenTab: (tab: Tab) => void;
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const labels = useActionLabels();

    const counts = useMemo(() => {
        const tally: Partial<Record<Filter, number>> = { all: report.people.length };
        report.people.forEach((person) => {
            const key = filterOf(person);
            tally[key] = (tally[key] ?? 0) + 1;
        });
        return tally;
    }, [report.people]);

    const visibleFilters = FILTERS.filter((key) => key === 'all' || (counts[key] ?? 0) > 0);
    const firstChange = (['create', 'update', 'restore', 'deactivate'] as const).find((key) => (counts[key] ?? 0) > 0);
    // The component is keyed on the preview, so a new preview starts here
    // again rather than keeping a filter its numbers may no longer have.
    const [filter, setFilter] = useState<Filter>(firstChange ?? 'all');
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const selectFilter = (key: Filter) => {
        setFilter(key);
        setPage(1);
    };

    // Which deleted clients come back. Ticked to begin with when the restore
    // option is on, as the preview then lists them as restored; a deleted
    // client can still be picked by hand with the option off.
    const restorable = useMemo(() => report.people.filter(isRestorable), [report.people]);
    const [restore, setRestore] = useState(() => new Set(restorable.filter((person) => person.action === 'restore').map((person) => person.email)));
    const toggleRestore = (email: string, on: boolean) =>
        setRestore((current) => {
            const next = new Set(current);
            if (on) next.add(email);
            else next.delete(email);
            return next;
        });

    // Each row as the sync would now treat it: a restorable client is
    // restored when ticked and skipped when not.
    const people = useMemo(
        () =>
            report.people.map(
                (person): SyncPerson =>
                    !isRestorable(person)
                        ? person
                        : restore.has(person.email)
                          ? { ...person, action: 'restore', reason: undefined }
                          : { ...person, action: 'skip', reason: 'not_ticked' },
            ),
        [report.people, restore],
    );

    // A preview vouches for a sync for ten minutes; after that, the button
    // asks for a fresh one rather than trusting an old list.
    const previewedAt = (report.finished_at ?? 0) * 1000;
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 15_000);
        return () => window.clearInterval(id);
    }, []);
    const stale = report.finished_at === null || now - previewedAt > PREVIEW_VALID_MS;
    const syncBlocked = stale || blockedReason !== null;

    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return people.filter(
            (person) =>
                (filter === 'all' || filterOf(person) === filter) &&
                (needle === '' || person.name.toLowerCase().includes(needle) || person.email.toLowerCase().includes(needle)),
        );
    }, [people, filter, query]);
    const lastPage = Math.max(1, Math.ceil(rows.length / PREVIEW_PAGE));
    const currentPage = Math.min(page, lastPage);
    const shown = rows.slice((currentPage - 1) * PREVIEW_PAGE, currentPage * PREVIEW_PAGE);

    const restored = restore.size;
    const changes = report.created + report.updated + restored + report.deactivated;
    const needsAttention = (counts.keep ?? 0) + (counts.error ?? 0) > 0;
    // Every active directory client is either still listed (updated or
    // unchanged) or about to be deactivated. A large share going at once is
    // far more often a narrowed filter than people leaving.
    const directoryClients = report.updated + report.unchanged + report.deactivated;
    const massDeactivation = report.deactivated > 1 && report.deactivated > directoryClients * DEACTIVATE_WARN_SHARE;
    const massWarning = t(
        'This would deactivate :count of the :total client accounts that came from the directory. If that is more than you expect, check the base DN and the additional filter first.',
        { count: report.deactivated, total: directoryClients },
    );

    // The rows on screen, filter and search applied, so one kind can be
    // exported on its own.
    const downloadCsv = () => {
        // A leading =, +, -, @ or control character makes a spreadsheet run
        // the cell as a formula; directory values are not ours to trust.
        const cell = (value: string) => `"${(/^[=+\-@\t\r]/.test(value) ? `'${value}` : value).replace(/"/g, '""')}"`;
        const lines = [
            [t('Name'), t('Email address'), t('What happens'), t('Detail')].map(cell).join(','),
            ...rows.map((person) => [person.name, person.email, labels[person.action], detailText(person, autoApprove, t)].map(cell).join(',')),
        ];
        // The byte order mark is what makes Excel read the file as UTF-8.
        const url = URL.createObjectURL(new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
        const link = Object.assign(document.createElement('a'), { href: url, download: `ldap-sync-preview-${filter}.csv` });
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    return (
        <section aria-labelledby="sync-preview-heading" className="space-y-4">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2
                    id="sync-preview-heading"
                    tabIndex={-1}
                    aria-describedby={report.status === 'failed' ? 'sync-preview-error' : undefined}
                    className={`text-base font-semibold ${FOCUS_RING}`}
                >
                    {t('Preview')}
                </h2>
                {report.finished_at !== null && (
                    <p className="text-muted-foreground text-sm">{t('Previewed :date', { date: dateTime(new Date(previewedAt).toISOString()) })}</p>
                )}
            </div>

            {report.status === 'failed' ? (
                <div className="border-destructive/50 rounded-lg border px-4 py-3 text-sm">
                    <p id="sync-preview-error" className={`font-medium ${DANGER_TEXT}`}>
                        {report.error}
                    </p>
                    <p className="text-muted-foreground mt-1">
                        {t('Check the connection settings, then test them.')}{' '}
                        <button
                            type="button"
                            onClick={() => onOpenTab('connection')}
                            className={`text-foreground underline underline-offset-4 ${FOCUS_RING}`}
                        >
                            {t('Open the Connection tab')}
                        </button>{' '}
                        ·{' '}
                        <button
                            type="button"
                            onClick={() => onOpenTab('test')}
                            className={`text-foreground underline underline-offset-4 ${FOCUS_RING}`}
                        >
                            {t('Test connection')}
                        </button>
                    </p>
                </div>
            ) : report.people.length === 0 ? (
                <div className="rounded-lg border px-4 py-8 text-center text-sm">
                    <p>{t('The directory returned nobody.')}</p>
                    <p className="text-muted-foreground mt-1">
                        {t('Check the base DN and the additional filter.')}{' '}
                        <button
                            type="button"
                            onClick={() => onOpenTab('directory')}
                            className={`text-foreground underline underline-offset-4 ${FOCUS_RING}`}
                        >
                            {t('Open the Directory tab')}
                        </button>
                    </p>
                </div>
            ) : (
                <>
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div role="tablist" aria-label={t('Filter by what would happen')} className="flex flex-wrap gap-1 border-b">
                            {visibleFilters.map((key) => (
                                <button
                                    type="button"
                                    role="tab"
                                    key={key}
                                    id={`sync-filter-${key}`}
                                    aria-selected={filter === key}
                                    aria-controls="sync-preview-table"
                                    tabIndex={filter === key ? 0 : -1}
                                    onClick={() => selectFilter(key)}
                                    onKeyDown={(event) => moveBetweenTabs(event, visibleFilters, filter, selectFilter)}
                                    className={`focus-visible:ring-ring -mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm outline-none focus-visible:ring-2 ${filter === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground hover:text-foreground border-transparent'}`}
                                >
                                    {labels[key]}
                                    <span
                                        className={`tabular-nums ${key === 'deactivate' || key === 'error' ? DANGER_TEXT : 'text-muted-foreground'}`}
                                    >
                                        {counts[key] ?? 0}
                                    </span>
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center gap-2">
                            {report.people.length > SEARCH_FROM && (
                                <div className="relative">
                                    <Search
                                        className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                        aria-hidden
                                    />
                                    <Input
                                        type="search"
                                        aria-controls="sync-preview-table"
                                        value={query}
                                        onChange={(e) => {
                                            setQuery(e.target.value);
                                            setPage(1);
                                        }}
                                        placeholder={t('Search name or address')}
                                        aria-label={t('Search name or address')}
                                        className="w-56 pl-8"
                                    />
                                </div>
                            )}
                            <Button type="button" variant="ghost" size="sm" onClick={downloadCsv} disabled={rows.length === 0}>
                                <Download className="size-4" aria-hidden />
                                {t('Download CSV')}
                            </Button>
                        </div>
                    </div>

                    <p role="status" className="sr-only">
                        {rows.length === 0
                            ? t('Nobody matches.')
                            : t('Showing :from–:to of :total', {
                                  from: (currentPage - 1) * PREVIEW_PAGE + 1,
                                  to: (currentPage - 1) * PREVIEW_PAGE + shown.length,
                                  total: rows.length,
                              })}
                    </p>

                    {restorable.length > 0 && (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <p className="text-muted-foreground">
                                {t('Restoring :selected of the :total deleted clients that can come back.', {
                                    selected: restored,
                                    total: restorable.length,
                                })}
                            </p>
                            <Button
                                type="button"
                                variant="link"
                                className="h-auto p-0 dark:text-violet-300"
                                onClick={() => setRestore(new Set(restorable.map((person) => person.email)))}
                            >
                                {t('Select all')}
                            </Button>
                            <Button type="button" variant="link" className="h-auto p-0 dark:text-violet-300" onClick={() => setRestore(new Set())}>
                                {t('Select none')}
                            </Button>
                        </div>
                    )}

                    <div id="sync-preview-table" role="tabpanel" tabIndex={0} aria-labelledby={`sync-filter-${filter}`} className={FOCUS_RING}>
                        <TableShell
                            columns={[
                                ...(restorable.length > 0 ? [{ label: t('Restore'), srOnly: true as const }] : []),
                                t('Person'),
                                t('What happens'),
                                t('Detail'),
                            ]}
                            isEmpty={rows.length === 0}
                            emptyMessage={t('Nobody matches.')}
                        >
                            {shown.map((person) => (
                                <tr key={person.email} className="border-b last:border-0">
                                    {restorable.length > 0 && (
                                        <td className="w-10 py-2.5 pl-4">
                                            {filterOf(person) === 'restore' && (
                                                <Checkbox
                                                    checked={restore.has(person.email)}
                                                    onCheckedChange={(checked) => toggleRestore(person.email, checked === true)}
                                                    aria-label={t('Restore :name', { name: person.name })}
                                                />
                                            )}
                                        </td>
                                    )}
                                    <td className="px-4 py-2.5">
                                        <div className="font-medium">{person.name}</div>
                                        <div className="text-muted-foreground text-xs">{person.email}</div>
                                    </td>
                                    <td className="px-4 py-2.5 whitespace-nowrap">
                                        <ActionBadge action={person.action} label={labels[person.action]} />
                                    </td>
                                    <td className={`px-4 py-2.5 ${person.action === 'error' ? DANGER_TEXT : 'text-muted-foreground'}`}>
                                        {detailText(person, autoApprove, t)}
                                    </td>
                                </tr>
                            ))}
                        </TableShell>
                    </div>

                    <ListPager page={currentPage} pageSize={PREVIEW_PAGE} total={rows.length} onPage={setPage} />

                    <div className="space-y-3 border-t pt-4">
                        {changes === 0 ? (
                            <p className="text-sm">
                                {needsAttention
                                    ? t(
                                          'A sync would change nothing. Check the rows marked Keep or Error: kept accounts are only deactivated with the option above on.',
                                      )
                                    : t('Everyone already matches the directory. A sync would change nothing.')}
                            </p>
                        ) : (
                            <>
                                {massDeactivation && !stale && (
                                    <Alert variant="destructive" className={`border-red-300 dark:border-red-800 ${DANGER_TEXT}`}>
                                        <AlertDescription id="sync-mass-warning" className="text-inherit">
                                            {massWarning}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {/* The button stays where it was, and focusable, whatever
                                    stops it; the line beside it says why. */}
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                type="button"
                                                className={`aria-disabled:pointer-events-none aria-disabled:opacity-50 ${report.deactivated > 0 ? DANGER_FILL : ''}`}
                                                aria-disabled={syncBlocked}
                                                aria-describedby={massDeactivation && !stale ? 'sync-mass-warning sync-now-note' : 'sync-now-note'}
                                                onClick={(event) => syncBlocked && event.preventDefault()}
                                            >
                                                {t('Sync now…')}
                                            </Button>
                                        }
                                        title={t('Sync with the directory?')}
                                        description={`${massDeactivation ? `${massWarning} ` : ''}${t(
                                            'This creates :created, updates :updated, restores :restored and deactivates :deactivated client accounts, as listed in this preview. It runs in the background. Staff and local accounts are not touched.',
                                            {
                                                created: report.created,
                                                updated: report.updated,
                                                restored,
                                                deactivated: report.deactivated,
                                            },
                                        )}`}
                                        confirmLabel={t('Sync now')}
                                        destructive={report.deactivated > 0}
                                        onConfirm={() => onSync([...restore])}
                                    />
                                    <p
                                        id="sync-now-note"
                                        className={`text-sm ${!stale && blockedReason === null && report.deactivated > 0 ? DANGER_TEXT : 'text-muted-foreground'}`}
                                    >
                                        {stale ? (
                                            <>
                                                {t('This preview is more than 10 minutes old.')}{' '}
                                                <Button
                                                    type="button"
                                                    variant="link"
                                                    className="h-auto p-0 aria-disabled:pointer-events-none aria-disabled:opacity-50 dark:text-violet-300"
                                                    aria-disabled={blockedReason !== null}
                                                    onClick={() => blockedReason === null && onPreview()}
                                                >
                                                    {t('Preview again')}
                                                </Button>{' '}
                                                {t('before syncing.')}
                                            </>
                                        ) : (
                                            (blockedReason ??
                                            (report.deactivated > 0
                                                ? t('Accounts to deactivate: :count.', { count: report.deactivated })
                                                : t('Nobody is deactivated by this sync.')))
                                        )}
                                    </p>
                                </div>
                            </>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}

/**
 * Tinted badges rather than the solid success/destructive variants: white
 * on those fills is under 4.5:1 at this size, a tint with dark text is not.
 */
const BADGE_TONE: Record<SyncAction, string> = {
    create: 'border-transparent bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    update: 'border-transparent bg-secondary text-secondary-foreground',
    restore: 'border-transparent bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    deactivate: 'border-transparent bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    keep: 'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    // Outlined, so an error does not read as a deactivation at a glance.
    error: 'border-red-500 bg-transparent text-red-800 dark:border-red-500 dark:text-red-300',
    skip: 'text-muted-foreground',
    unchanged: 'text-muted-foreground',
};

function ActionBadge({ action, label }: { action: SyncAction; label: string }) {
    return (
        <Badge variant="outline" className={BADGE_TONE[action]}>
            {label}
        </Badge>
    );
}

/** The plain-language reason behind a row, shared by the table and the CSV. */
function detailText(person: SyncPerson, autoApprove: boolean, t: (key: string, replace?: Record<string, string | number>) => string): string {
    switch (person.action) {
        case 'create':
            return autoApprove ? t('New client account') : t('New client account, waiting for approval');
        case 'update':
            return [
                person.previous_name !== undefined ? t('Name: :from → :to', { from: person.previous_name, to: person.name }) : null,
                person.moved ? t('Moved to a different place in the directory') : null,
            ]
                .filter(Boolean)
                .join(' · ');
        case 'restore':
            return t('Deleted by an administrator; the deletion is undone');
        case 'deactivate':
            return t('No longer in the directory');
        case 'keep':
            return t('No longer in the directory; kept because deactivation is off');
        case 'error':
            return person.error ?? '';
        case 'skip':
            return (
                {
                    staff: t('Staff account; the directory never manages staff'),
                    local: t('Has a local password, so it is not a directory account'),
                    deleted: t('The address belongs to a deleted account'),
                    deleted_by_owner: t('Deleted by its owner; never restored'),
                    not_ticked: t('Deleted by an administrator; tick it to restore it'),
                    provisioning_off: t("No account, and accounts aren't created on first sign-in"),
                }[person.reason ?? ''] ??
                person.reason ??
                ''
            );
        default:
            return t('Already matches the directory');
    }
}

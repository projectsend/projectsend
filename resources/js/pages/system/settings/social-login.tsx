import { Head, useForm } from '@inertiajs/react';
import { Check, ChevronDown, Copy } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ProviderIcon } from '@/components/provider-icon';
import { SaveButton } from '@/components/save-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ProviderSettings {
    provider: string;
    label: string;
    enabled: boolean;
    client_id: string | null;
    has_client_secret: boolean;
    issuer_url: string | null;
    tenant_id: string | null;
    require_verified_email: boolean;
    allowed_domains: string | null;
    auto_provision: boolean;
    auto_approve: boolean;
    needs_issuer_url: boolean;
    needs_tenant_id: boolean;
    can_report_verified_email: boolean;
    redirect_uri: string;
}

export default function SocialLoginSettingsPage({ providers }: { providers: ProviderSettings[] }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState<string | null>(providers.find((provider) => provider.enabled)?.provider ?? null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Social login'), href: '/system/settings/social-login' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Social login')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Social login')}
                    description={t('Let people sign in with an account they already have somewhere else.')}
                />

                <div className="max-w-3xl space-y-3">
                    {providers.map((provider) => (
                        <ProviderCard
                            key={provider.provider}
                            provider={provider}
                            open={open === provider.provider}
                            onToggle={() => setOpen(open === provider.provider ? null : provider.provider)}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function ProviderCard({ provider, open, onToggle }: { provider: ProviderSettings; open: boolean; onToggle: () => void }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const form = useForm({
        enabled: provider.enabled,
        client_id: provider.client_id ?? '',
        client_secret: '',
        issuer_url: provider.issuer_url ?? '',
        tenant_id: provider.tenant_id ?? '',
        require_verified_email: provider.require_verified_email,
        allowed_domains: provider.allowed_domains ?? '',
        auto_provision: provider.auto_provision,
        auto_approve: provider.auto_approve,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('system-settings.social-login.update', { provider: provider.provider }), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const copyRedirectUri = () => {
        void navigator.clipboard?.writeText(provider.redirect_uri);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="rounded-md border">
            <button type="button" onClick={onToggle} className="hover:bg-muted/50 flex w-full items-center gap-3 p-4 text-left">
                <ProviderIcon provider={provider.provider} className="h-8 w-8 text-base" />

                <div className="flex-1">
                    <p className="font-medium">{provider.label}</p>
                    <p className="text-muted-foreground text-sm">{form.data.enabled ? t('Enabled') : t('Disabled')}</p>
                </div>

                <ChevronDown className={`text-muted-foreground h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <form onSubmit={submit} className="space-y-6 border-t p-4">
                    <div className="flex items-center space-x-3">
                        <Checkbox
                            id={`${provider.provider}-enabled`}
                            checked={form.data.enabled}
                            onCheckedChange={(checked) => form.setData('enabled', checked === true)}
                        />
                        <Label htmlFor={`${provider.provider}-enabled`}>{t('Allow signing in with :provider', { provider: provider.label })}</Label>
                    </div>

                    {/* The single most common configuration failure is pasting
                        the wrong URI into the provider's console. */}
                    <div className="grid gap-2">
                        <Label>{t('Redirect URI')}</Label>
                        <div className="flex gap-2">
                            <Input readOnly value={provider.redirect_uri} className="font-mono text-xs" />
                            <Button type="button" variant="outline" size="icon" onClick={copyRedirectUri}>
                                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                            </Button>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {t('Register this exact address with :provider. It must match character for character.', {
                                provider: provider.label,
                            })}
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${provider.provider}-client-id`}>{t('Client ID')}</Label>
                        <Input
                            id={`${provider.provider}-client-id`}
                            value={form.data.client_id}
                            onChange={(e) => form.setData('client_id', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={form.errors.client_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${provider.provider}-client-secret`}>{t('Client secret')}</Label>
                        <Input
                            id={`${provider.provider}-client-secret`}
                            type="password"
                            value={form.data.client_secret}
                            onChange={(e) => form.setData('client_secret', e.target.value)}
                            autoComplete="new-password"
                            placeholder={provider.has_client_secret ? '••••••••••••' : ''}
                        />
                        <p className="text-muted-foreground text-sm">
                            {provider.has_client_secret
                                ? t('A secret is stored. Leave this blank to keep it.')
                                : t('Stored encrypted, and never shown again.')}
                        </p>
                        <InputError message={form.errors.client_secret} />
                    </div>

                    {provider.needs_issuer_url && (
                        <div className="grid gap-2">
                            <Label htmlFor={`${provider.provider}-issuer`}>{t('Issuer URL')}</Label>
                            <Input
                                id={`${provider.provider}-issuer`}
                                value={form.data.issuer_url}
                                onChange={(e) => form.setData('issuer_url', e.target.value)}
                                placeholder="https://auth.example.com/realms/main"
                            />
                            <p className="text-muted-foreground text-sm">
                                {t('The endpoints are read from this address, so it is the whole configuration. Must be https.')}
                            </p>
                            <InputError message={form.errors.issuer_url} />
                        </div>
                    )}

                    {provider.needs_tenant_id && (
                        <div className="grid gap-2">
                            <Label htmlFor={`${provider.provider}-tenant`}>{t('Directory (tenant) ID')}</Label>
                            <Input
                                id={`${provider.provider}-tenant`}
                                value={form.data.tenant_id}
                                onChange={(e) => form.setData('tenant_id', e.target.value)}
                                placeholder="00000000-0000-0000-0000-000000000000"
                            />
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Your own tenant, not "common". Microsoft lets a user change the email address on their account, so a sign-in is only trustworthy when the token came from the tenant you named here.',
                                )}
                            </p>
                            <InputError message={form.errors.tenant_id} />
                        </div>
                    )}

                    <div className="space-y-3">
                        <div className="flex items-start space-x-3">
                            <Checkbox
                                id={`${provider.provider}-verified`}
                                checked={form.data.require_verified_email}
                                onCheckedChange={(checked) => form.setData('require_verified_email', checked === true)}
                            />
                            <div className="grid gap-1">
                                <Label htmlFor={`${provider.provider}-verified`}>{t('Require a verified email address')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'An address the provider has not confirmed can never be matched to an account that already exists here. Turning this off means anyone who can register at your provider under someone else’s address can sign in as them.',
                                    )}
                                </p>
                            </div>
                        </div>

                        {!provider.can_report_verified_email && form.data.require_verified_email && (
                            <Alert>
                                <AlertDescription>
                                    {t(
                                        ':provider never reports whether an address is verified, so with this on it can create new accounts but can never sign in to an existing one. Connecting it from Settings while already signed in still works.',
                                        { provider: provider.label },
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${provider.provider}-domains`}>{t('Allowed email domains')}</Label>
                        <Input
                            id={`${provider.provider}-domains`}
                            value={form.data.allowed_domains}
                            onChange={(e) => form.setData('allowed_domains', e.target.value)}
                            placeholder="example.com, example.co.uk"
                        />
                        <p className="text-muted-foreground text-sm">
                            {t('Comma separated. Leave blank to allow any address.')}
                        </p>
                        <InputError message={form.errors.allowed_domains} />
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-start space-x-3">
                            <Checkbox
                                id={`${provider.provider}-provision`}
                                checked={form.data.auto_provision}
                                onCheckedChange={(checked) => form.setData('auto_provision', checked === true)}
                            />
                            <div className="grid gap-1">
                                <Label htmlFor={`${provider.provider}-provision`}>{t('Create an account on first sign-in')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('Always a client account. Staff accounts are only ever created by an administrator.')}
                                </p>
                            </div>
                        </div>

                        {form.data.auto_provision && (
                            <div className="ml-7 flex items-start space-x-3">
                                <Checkbox
                                    id={`${provider.provider}-approve`}
                                    checked={form.data.auto_approve}
                                    onCheckedChange={(checked) => form.setData('auto_approve', checked === true)}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor={`${provider.provider}-approve`}>{t('Approve those accounts automatically')}</Label>
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'Off means they wait in Account requests. An address the provider did not verify always waits, whatever this says.',
                                        )}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    <SaveButton processing={form.processing} recentlySuccessful={form.recentlySuccessful} />
                </form>
            )}
        </div>
    );
}

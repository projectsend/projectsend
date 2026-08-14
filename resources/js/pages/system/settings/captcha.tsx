import { Head, router, useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
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
import { type BreadcrumbItem } from '@/types';

interface ProviderSettings {
    provider: string;
    label: string;
    site_key: string | null;
    has_secret_key: boolean;
    score_threshold: number;
    uses_score: boolean;
}

interface CaptchaSettingsProps {
    provider: string;
    key_source: 'managed' | 'own';
    managed_keys_available: boolean;
    providers: ProviderSettings[];
    forms: { login: boolean; registration: boolean; password_reset: boolean; public_comments: boolean };
    active: boolean;
    using_managed_keys: boolean;
    last_error: { at: string; codes: string[]; our_credentials: boolean } | null;
    test_result: { ok: boolean; message: string } | null;
}

export default function CaptchaSettings({
    provider,
    key_source,
    managed_keys_available,
    providers,
    forms,
    active,
    using_managed_keys,
    last_error,
    test_result,
}: CaptchaSettingsProps) {
    const { t } = useTranslation();
    const [testing, setTesting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('CAPTCHA'), href: '/system/settings/captcha' },
    ];

    const selected = providers.find((candidate) => candidate.provider === provider) ?? null;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        key_source,
        provider,
        site_key: selected?.site_key ?? '',
        secret_key: '',
        score_threshold: String(selected?.score_threshold ?? 0.5),
        on_login: forms.login,
        on_registration: forms.registration,
        on_password_reset: forms.password_reset,
        on_public_comments: forms.public_comments,
    });

    // Switching provider swaps in that provider's stored key, so the two
    // never drift apart on screen. Keys are kept per provider precisely so
    // that comparing two of them costs nothing.
    const chooseProvider = (value: string) => {
        const next = providers.find((candidate) => candidate.provider === value) ?? null;

        setData((current) => ({
            ...current,
            provider: value,
            site_key: next?.site_key ?? '',
            secret_key: '',
            score_threshold: String(next?.score_threshold ?? 0.5),
        }));
    };

    const current = providers.find((candidate) => candidate.provider === data.provider) ?? null;
    const configuringOwnKeys = data.key_source === 'own';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.captcha.update'), { preserveScroll: true });
    };

    const testKeys = () => {
        setTesting(true);

        router.post(
            route('system-settings.captcha.test'),
            { provider: data.provider, secret_key: data.secret_key },
            { preserveScroll: true, onFinish: () => setTesting(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('CAPTCHA')} />

            <div className="px-4 py-6">
                <Heading title={t('CAPTCHA')} description={t('Protect your public forms from automated abuse')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    {/* Being half-configured is the same as being switched off,
                        and an operator who believes they are covered when they
                        are not is worse off than one who knows they are not. */}
                    {!active && (
                        <Alert>
                            <AlertDescription>
                                {t('CAPTCHA is currently switched off. Choose a provider and enter its keys to switch it on.')}
                            </AlertDescription>
                        </Alert>
                    )}

                    {last_error && (
                        <Alert variant="destructive">
                            <CircleAlert className="size-4" />
                            <AlertDescription>
                                {last_error.our_credentials
                                    ? t(
                                          'Your provider rejected this installation’s secret key, so protected forms are being let through unchecked. Check the secret key below.',
                                      )
                                    : t(
                                          'Verification could not be reached recently, so protected forms are being let through unchecked. Nobody is locked out.',
                                      )}
                            </AlertDescription>
                        </Alert>
                    )}

                    {test_result && <TestResultAlert ok={test_result.ok}>{test_result.message}</TestResultAlert>}

                    {managed_keys_available && (
                        <div className="grid gap-3">
                            <Label>{t('Protection')}</Label>

                            <label className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="key_source"
                                    className="mt-1"
                                    checked={data.key_source === 'managed'}
                                    onChange={() => setData('key_source', 'managed')}
                                />
                                <span className="text-sm">
                                    <span className="font-medium">{t('Use ProjectSend’s protection')}</span>
                                    <span className="text-muted-foreground block">
                                        {t('We protect your public forms for you. Nothing to set up, no keys to manage.')}
                                    </span>
                                </span>
                            </label>

                            <label className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="key_source"
                                    className="mt-1"
                                    checked={configuringOwnKeys}
                                    onChange={() => setData('key_source', 'own')}
                                />
                                <span className="text-sm">
                                    <span className="font-medium">{t('Configure it myself')}</span>
                                    <span className="text-muted-foreground block">{t('Use your own reCAPTCHA or Turnstile account.')}</span>
                                </span>
                            </label>

                            <InputError message={errors.key_source} />
                        </div>
                    )}

                    {using_managed_keys && data.key_source === 'managed' && (
                        <p className="text-muted-foreground text-sm">
                            {t('Your public forms are protected. You can switch to your own keys at any time.')}
                        </p>
                    )}

                    {configuringOwnKeys && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="provider">{t('Service')}</Label>

                                <Select value={data.provider} onValueChange={chooseProvider}>
                                    <SelectTrigger id="provider" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('Off — no CAPTCHA')}</SelectItem>
                                        {providers.map((candidate) => (
                                            <SelectItem key={candidate.provider} value={candidate.provider}>
                                                {candidate.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <InputError message={errors.provider} />
                            </div>

                            {current && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="site_key">{t('Site key')}</Label>
                                        <Input
                                            id="site_key"
                                            value={data.site_key}
                                            onChange={(e) => setData('site_key', e.target.value)}
                                            autoComplete="off"
                                        />
                                        <p className="text-muted-foreground text-sm">
                                            {t('The public key. It appears in your pages’ source, which is expected.')}
                                        </p>
                                        <InputError message={errors.site_key} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="secret_key">{t('Secret key')}</Label>
                                        <Input
                                            id="secret_key"
                                            type="password"
                                            value={data.secret_key}
                                            onChange={(e) => setData('secret_key', e.target.value)}
                                            autoComplete="new-password"
                                            placeholder={current.has_secret_key ? t('Stored — leave blank to keep it') : ''}
                                        />
                                        <p className="text-muted-foreground text-sm">
                                            {t('Stored encrypted and never shown again, not even to you.')}
                                        </p>
                                        <InputError message={errors.secret_key} />
                                    </div>

                                    {current.uses_score && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="score_threshold">{t('Score threshold')}</Label>
                                            <Input
                                                id="score_threshold"
                                                type="number"
                                                min="0"
                                                max="1"
                                                step="0.1"
                                                value={data.score_threshold}
                                                onChange={(e) => setData('score_threshold', e.target.value)}
                                            />
                                            <p className="text-muted-foreground text-sm">
                                                {t(
                                                    'Between 0.0 (treat everyone as a bot) and 1.0 (treat everyone as human). The usual value is 0.5.',
                                                )}
                                            </p>
                                            <InputError message={errors.score_threshold} />
                                        </div>
                                    )}

                                    <div>
                                        <Button type="button" variant="outline" onClick={testKeys} disabled={testing}>
                                            {testing ? t('Testing…') : t('Test keys')}
                                        </Button>
                                        <p className="text-muted-foreground mt-2 text-sm">
                                            {t('Checks the secret key with your provider. Nobody has to solve a challenge.')}
                                        </p>
                                    </div>
                                </>
                            )}
                        </>
                    )}

                    <div className="grid gap-3">
                        <Label>{t('Protect these forms')}</Label>

                        {(
                            [
                                ['on_login', t('Sign in')],
                                ['on_registration', t('Client registration')],
                                ['on_password_reset', t('Password reset')],
                                ['on_public_comments', t('Comments from visitors')],
                            ] as const
                        ).map(([field, label]) => (
                            <label key={field} className="flex items-center gap-3 text-sm">
                                <Checkbox checked={data[field]} onCheckedChange={(checked) => setData(field, checked === true)} />
                                {label}
                            </label>
                        ))}

                        <p className="text-muted-foreground text-sm">
                            {t('Signed-in visitors are never asked to solve a challenge when commenting.')}
                        </p>
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}

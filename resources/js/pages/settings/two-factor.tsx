import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { ShieldAlert } from 'lucide-react';

interface TwoFactorProps {
    enforced: boolean;
    enabled: boolean;
    pending: boolean;
    qr_code_svg: string | null;
    secret: string | null;
    recovery_codes: string[] | null;
}

export default function TwoFactor({ enforced, enabled, pending, qr_code_svg, secret, recovery_codes }: TwoFactorProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Two-factor authentication'),
            href: '/settings/two-factor',
        },
    ];

    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({});
    const regenerateForm = useForm({});

    const confirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirmForm.post(route('two-factor.confirm'), {
            onSuccess: () => confirmForm.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Two-factor authentication')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Two-factor authentication')}
                        description={t('Add a second step to your login using an authenticator app.')}
                    />

                    {enforced && !enabled && (
                        <Alert>
                            <ShieldAlert className="size-4" />
                            <AlertDescription>
                                {t('Your administrator requires two-factor authentication on this account. Set it up below to continue.')}
                            </AlertDescription>
                        </Alert>
                    )}

                    {!enabled && !pending && (
                        <div className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                {t('When enabled, you will be asked for a secure code from your phone when logging in.')}
                            </p>
                            <Button onClick={() => enableForm.post(route('two-factor.enable'))} disabled={enableForm.processing}>
                                {t('Enable two-factor authentication')}
                            </Button>
                        </div>
                    )}

                    {pending && (
                        <div className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                {t('Scan this QR code with your authenticator app, then enter the six-digit code to confirm.')}
                            </p>

                            {qr_code_svg && (
                                <div className="inline-block rounded-lg border bg-white p-3" dangerouslySetInnerHTML={{ __html: qr_code_svg }} />
                            )}

                            {secret && (
                                <p className="text-muted-foreground text-sm">
                                    {t('Or enter this key manually:')} <code className="font-mono select-all">{secret}</code>
                                </p>
                            )}

                            <form onSubmit={confirm} className="flex max-w-xs flex-col gap-2">
                                <Label htmlFor="code">{t('Authentication code')}</Label>
                                <Input
                                    id="code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    placeholder="123456"
                                    value={confirmForm.data.code}
                                    onChange={(e) => confirmForm.setData('code', e.target.value)}
                                />
                                <InputError message={confirmForm.errors.code} />
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={confirmForm.processing}>
                                        {t('Confirm')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => disableForm.delete(route('two-factor.disable'))}
                                        disabled={disableForm.processing}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    )}

                    {enabled && (
                        <div className="space-y-4">
                            <p className="text-sm font-medium text-green-600">{t('Two-factor authentication is enabled.')}</p>

                            {recovery_codes && (
                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'Store these recovery codes somewhere safe. Each one can be used once if you lose access to your authenticator app.',
                                        )}
                                    </p>
                                    <div className="bg-muted grid max-w-md grid-cols-2 gap-1 rounded-lg p-4 font-mono text-sm">
                                        {recovery_codes.map((code) => (
                                            <span key={code} className="select-all">
                                                {code}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="flex gap-2">
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="outline" disabled={regenerateForm.processing}>
                                            {t('Regenerate recovery codes')}
                                        </Button>
                                    }
                                    title={t('Regenerate recovery codes?')}
                                    description={t('Your existing recovery codes will stop working immediately.')}
                                    confirmLabel={t('Regenerate recovery codes')}
                                    destructive={false}
                                    onConfirm={() => regenerateForm.post(route('two-factor.recovery-codes'))}
                                />
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="destructive" disabled={disableForm.processing}>
                                            {t('Disable')}
                                        </Button>
                                    }
                                    title={t('Disable two-factor authentication?')}
                                    description={t('Your account will no longer require a second code at login, making it less secure.')}
                                    confirmLabel={t('Disable')}
                                    onConfirm={() => disableForm.delete(route('two-factor.disable'))}
                                />
                            </div>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

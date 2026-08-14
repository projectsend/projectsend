import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

export default function TwoFactorChallenge() {
    const { t } = useTranslation();
    const [useRecovery, setUseRecovery] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.challenge'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    return (
        <AuthLayout
            title={t('Two-factor authentication')}
            description={useRecovery ? t('Enter one of your recovery codes.') : t('Enter the six-digit code from your authenticator app.')}
        >
            <Head title={t('Two-factor authentication')} />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    {!useRecovery ? (
                        <div className="grid gap-2">
                            <Label htmlFor="code">{t('Authentication code')}</Label>
                            <Input
                                id="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                required
                                autoFocus
                                placeholder="123456"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                            />
                            <InputError message={errors.code} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="recovery_code">{t('Recovery code')}</Label>
                            <Input
                                id="recovery_code"
                                type="text"
                                required
                                autoFocus
                                value={data.recovery_code}
                                onChange={(e) => setData('recovery_code', e.target.value)}
                            />
                            <InputError message={errors.recovery_code} />
                        </div>
                    )}

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Log in')}
                    </Button>

                    <button
                        type="button"
                        className="text-muted-foreground hover:text-foreground text-center text-sm underline"
                        onClick={() => {
                            setUseRecovery(!useRecovery);
                            reset('code', 'recovery_code');
                        }}
                    >
                        {useRecovery ? t('Use an authentication code') : t('Use a recovery code')}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}

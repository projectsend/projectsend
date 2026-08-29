// Components
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title={t('Confirm your password')}
            description={t('This is a secure area of the application. Please confirm your password before continuing.')}
        >
            <Head title={t('Confirm password')} />

            <form onSubmit={submit}>
                <div className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('Password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            placeholder={t('Password')}
                            autoComplete="current-password"
                            value={data.password}
                            autoFocus
                            onChange={(e) => setData('password', e.target.value)}
                        />

                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center">
                        <Button className="w-full" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            {t('Confirm password')}
                        </Button>
                    </div>
                </div>
            </form>
        </AuthLayout>
    );
}

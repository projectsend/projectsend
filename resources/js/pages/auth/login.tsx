import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useRef } from 'react';

import { CaptchaWidget, type CaptchaHandle } from '@/components/captcha-widget';
import InputError from '@/components/input-error';
import { SocialLoginButtons } from '@/components/social-login-buttons';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';
import { type SharedData } from '@/types';

interface LoginForm {
    [key: string]: string | boolean;
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

export default function Login({ status, canResetPassword, canRegister }: LoginProps) {
    const { t } = useTranslation();
    const { flash } = usePage<SharedData>().props;
    const captcha = useRef<CaptchaHandle>(null);
    const captchaToken = useRef<string | null>(null);

    const { data, setData, post, processing, errors, reset, transform } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    // transform(), not setData() in the handler: setData is asynchronous,
    // so a token written there would not be in the payload this submit
    // sends. This runs against the data on its way out.
    transform((current) => ({ ...current, captcha_token: captchaToken.current ?? '' }));

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        captchaToken.current = await captcha.current?.execute() ?? null;

        post(route('login'), {
            onFinish: () => reset('password'),
            // The token is spent whether or not the password was right, so
            // a wrong password must not leave a dead one in the form.
            onError: () => captcha.current?.reset(),
        });
    };

    return (
        <AuthLayout title={t('Log in to your account')} description={t('Enter your email and password below to log in')}>
            <Head title={t('Log in')} />

            {/* The app-wide Toaster lives in the authenticated layout, so a
                flash that redirects here would otherwise be silent — and the
                messages that land here are refusals from a provider sign-in
                that tell somebody what to do next. They stay on screen. */}
            {flash?.error && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{flash.error}</AlertDescription>
                </Alert>
            )}

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">{t('Password')}</Label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                                    {t('Forgot password?')}
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder={t('Password')}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" tabIndex={3} />
                        <Label htmlFor="remember">{t('Remember me')}</Label>
                    </div>

                    <div>
                        <CaptchaWidget ref={captcha} action="login" />
                        <InputError message={errors.captcha_token} />
                    </div>

                    {/* Never disabled while the token is missing: a blocked
                        provider script would otherwise leave a dead button
                        and no explanation of why. */}
                    <Button type="submit" className="mt-4 w-full" tabIndex={4} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Log in')}
                    </Button>
                </div>

                <SocialLoginButtons />

                {canRegister && (
                    <div className="text-muted-foreground text-center text-sm">
                        {t("Don't have an account?")} <TextLink href={route('register')}>{t('Register')}</TextLink>
                    </div>
                )}
            </form>

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}

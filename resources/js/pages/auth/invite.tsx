import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordRequirements } from '@/components/password-requirements';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

interface InviteProps {
    token: string;
    email: string;
    name: string;
    status: string | null;
    /**
     * Whether the server already knows this link will be refused. False
     * for a token it cannot place, which is not the same thing — see
     * InvitationRedemptionController::findUsable().
     */
    expired: boolean;
}

interface AcceptInvitationForm {
    [key: string]: string;
    token: string;
    name: string;
    password: string;
    password_confirmation: string;
}

export default function AcceptInvitation({ token, email, name, status, expired }: InviteProps) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, reset } = useForm<AcceptInvitationForm>({
        token: token,
        name: name,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitations.accept', token), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const resend: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitations.resend', token));
    };

    // Said before the work rather than after it, the same courtesy
    // reset-password gives an expired link — an invitation legitimately
    // sits unopened for a while, and asking for a password only to refuse
    // it is a bad minute for somebody who did nothing wrong.
    if (expired) {
        return (
            <AuthLayout title={t('This invitation has expired')} description={t('Ask for a new one and it will arrive in a moment.')}>
                <Head title={t('This invitation has expired')} />

                {status && <p className="text-muted-foreground mb-4 text-center text-sm">{status}</p>}

                <form onSubmit={resend}>
                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Send me a new invitation')}
                    </Button>
                </form>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={t('Create your account')} description={t('Choose a name and password to finish setting up your account.')}>
            <Head title={t('Create your account')} />

            <form onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input id="email" type="email" value={email} readOnly className="mt-1 block w-full" />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('Password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <PasswordRequirements />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">{t('Confirm password')}</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <InputError message={errors.token} />

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Create account')}
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}

import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useRef } from 'react';

import { CaptchaWidget, type CaptchaHandle } from '@/components/captcha-widget';
import { ClientCustomFieldsSection, type CustomFieldDefinition } from '@/components/client-custom-fields-section';
import InputError from '@/components/input-error';
import { SocialLoginButtons } from '@/components/social-login-buttons';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordRequirements } from '@/components/password-requirements';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterForm {
    [key: string]: string | number[] | Record<string, string>;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    groups: number[];
    custom_field_values: Record<string, string>;
}

interface RegisterProps {
    auto_approve: boolean;
    selectable_groups: { id: number; name: string }[];
    custom_fields: CustomFieldDefinition[];
}

export default function Register({ auto_approve, selectable_groups, custom_fields }: RegisterProps) {
    const { t } = useTranslation();

    const captcha = useRef<CaptchaHandle>(null);
    const captchaToken = useRef<string | null>(null);

    const { data, setData, post, processing, errors, reset, transform } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        groups: [],
        custom_field_values: Object.fromEntries(custom_fields.map((field) => [field.id, field.type === 'checkbox' ? '0' : ''])),
    });

    const toggleGroup = (id: number, checked: boolean) => {
        setData('groups', checked ? [...data.groups, id] : data.groups.filter((groupId) => groupId !== id));
    };

    transform((current) => ({ ...current, captcha_token: captchaToken.current ?? '' }));

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        captchaToken.current = await captcha.current?.execute() ?? null;

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
            onError: () => captcha.current?.reset(),
        });
    };

    return (
        <AuthLayout
            title={t('Create a client account')}
            description={
                auto_approve
                    ? t('Register to access the files shared with you.')
                    : t('Register to request access. An administrator will review your request.')
            }
        >
            <Head title={t('Register')} />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
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
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
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

                    {selectable_groups.length > 0 && (
                        <div className="grid gap-2">
                            <Label>{t('Request membership to groups (optional)')}</Label>
                            <div className="grid gap-1.5">
                                {selectable_groups.map((group) => (
                                    <div key={group.id} className="flex items-center gap-2">
                                        <Checkbox
                                            id={`group-${group.id}`}
                                            checked={data.groups.includes(group.id)}
                                            onCheckedChange={(checked) => toggleGroup(group.id, checked === true)}
                                        />
                                        <Label htmlFor={`group-${group.id}`} className="font-normal">
                                            {group.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            <p className="text-muted-foreground text-sm">{t('An administrator will review your group requests.')}</p>
                            <InputError message={errors.groups} />
                        </div>
                    )}

                    <ClientCustomFieldsSection
                        fields={custom_fields}
                        values={data.custom_field_values}
                        onChange={(fieldId, value) => setData('custom_field_values', { ...data.custom_field_values, [fieldId]: value })}
                        errors={errors}
                    />

                    <div>
                        <CaptchaWidget ref={captcha} action="register" />
                        <InputError message={errors.captcha_token} />
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Register')}
                    </Button>
                </div>

                <SocialLoginButtons label={t('Or sign up with')} />

                <div className="text-muted-foreground text-center text-sm">
                    {t('Already have an account?')} <TextLink href={route('login')}>{t('Log in')}</TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}

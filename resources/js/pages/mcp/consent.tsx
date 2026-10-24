import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

interface ConsentProps {
    request_id: string;
    client: {
        name: string;
        identity: string;
        verified: boolean;
        logo_uri: string | null;
        client_uri: string | null;
        redirect_host: string;
        loopback: boolean;
    };
    actor: { name: string; email: string; site: string };
    grant: { kind: 'staff' | 'client'; default_access: 'read' | 'operate' };
}

interface ConsentForm {
    [key: string]: string;
    request_id: string;
    decision: 'allow' | 'deny';
    access: 'read' | 'operate';
    password: string;
}

/**
 * The consent an assistant sends the person to (design §4.2): who asks,
 * as whom, for what, and the password once more. Nothing is issued
 * until Allow.
 */
export default function Consent({ request_id, client, actor, grant }: ConsentProps) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm<ConsentForm>({
        request_id,
        decision: 'allow',
        access: grant.default_access,
        password: '',
    });

    const allow: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('mcp.oauth.authorize.store'));
    };

    const deny = () => {
        router.post(route('mcp.oauth.authorize.store'), { request_id, decision: 'deny' });
    };

    const accessOptions: { value: 'read' | 'operate'; title: string; description: string }[] = [
        {
            value: 'read',
            title: t('Read'),
            description: t('See your files, their private links and who downloaded them. Nothing changes.'),
        },
        {
            value: 'operate',
            title: t('Operate'),
            description: t(
                'Also save, upload and organise files in folders, share and revoke links, delete files, and on this plan manage client accounts and send them files and folders.',
            ),
        },
    ];

    return (
        <AuthLayout title={t('Connect :name', { name: client.name })} description={t('It wants to use your account on :site.', { site: actor.site })}>
            <Head title={t('Connect :name', { name: client.name })} />

            <form onSubmit={allow} className="flex flex-col gap-6">
                <div className="space-y-3 rounded-lg border p-4">
                    <div className="flex items-center gap-3">
                        {client.logo_uri ? (
                            <img src={client.logo_uri} alt="" className="size-10 rounded-md object-contain" />
                        ) : null}
                        <div className="min-w-0">
                            <p className="truncate font-medium">{client.name}</p>
                            <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                <span className="truncate">{client.identity}</span>
                                {client.verified ? (
                                    <Badge variant="secondary">{t('Verified')}</Badge>
                                ) : (
                                    <Badge variant="outline">{t('Unverified')}</Badge>
                                )}
                            </p>
                        </div>
                    </div>

                    <p className="text-muted-foreground text-sm">
                        {client.loopback
                            ? t('This app runs on your computer. After you approve, your browser sends the code to it directly.')
                            : t('After you approve, your browser returns to :host.', { host: client.redirect_host })}
                    </p>
                </div>

                <p className="text-sm">
                    {t('Acting as :name (:email).', { name: actor.name, email: actor.email })}
                </p>

                {grant.kind === 'staff' ? (
                    <fieldset className="space-y-2">
                        <legend className="mb-2 text-sm font-medium">{t('What it can do')}</legend>
                        {accessOptions.map((option) => (
                            <label
                                key={option.value}
                                className="hover:bg-muted/50 has-[:checked]:border-primary flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                            >
                                <input
                                    type="radio"
                                    name="access"
                                    value={option.value}
                                    checked={data.access === option.value}
                                    onChange={() => setData('access', option.value)}
                                    className="mt-1"
                                />
                                <span>
                                    <span className="block text-sm font-medium">{option.title}</span>
                                    <span className="text-muted-foreground block text-sm">{option.description}</span>
                                </span>
                            </label>
                        ))}
                        <InputError message={errors.access} />
                    </fieldset>
                ) : (
                    <div className="space-y-1">
                        <p className="text-sm font-medium">{t('What it can do')}</p>
                        <p className="text-muted-foreground text-sm">
                            {t('Save and upload your files, get their private links, see who downloaded them, and free up space. Only your own files.')}
                        </p>
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="password">{t('Confirm your password')}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoFocus
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Allow')}
                    </Button>
                    <Button type="button" variant="ghost" disabled={processing} onClick={deny}>
                        {t('Deny')}
                    </Button>
                </div>

                <p className="text-muted-foreground text-xs">
                    {grant.kind === 'staff'
                        ? t('Everything it does appears in your activity as \'AI assistant\' and you can disconnect it any time from Settings → AI connections.')
                        : t('Everything it does appears in your activity as \'AI assistant\' and you can disconnect it any time from your AI connections page.')}
                </p>
            </form>
        </AuthLayout>
    );
}

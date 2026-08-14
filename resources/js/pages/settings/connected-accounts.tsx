import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import { ProviderIcon } from '@/components/provider-icon';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';

interface ConnectedProvider {
    provider: string;
    label: string;
    connected: boolean;
    email: string | null;
    connected_at: string | null;
}

interface ConnectedAccountsProps {
    providers: ConnectedProvider[];
    has_local_password: boolean;
}

export default function ConnectedAccounts({ providers, has_local_password }: ConnectedAccountsProps) {
    const { t } = useTranslation();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [processing, setProcessing] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Connected accounts'), href: '/settings/connected-accounts' }];

    const connectedCount = providers.filter((provider) => provider.connected).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Connected accounts')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Connected accounts')}
                        description={t('Sign in with an account you already have somewhere else.')}
                    />

                    {errors.provider && (
                        <Alert variant="destructive">
                            <AlertDescription>{errors.provider}</AlertDescription>
                        </Alert>
                    )}

                    {providers.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('No sign-in providers have been set up for this site.')}
                        </p>
                    ) : (
                        <div className="divide-border divide-y rounded-md border">
                            {providers.map((provider) => (
                                <div key={provider.provider} className="flex items-center gap-4 p-4">
                                    <ProviderIcon provider={provider.provider} className="h-8 w-8 text-base" />

                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium">{provider.label}</p>
                                        <p className="text-muted-foreground truncate text-sm">
                                            {provider.connected
                                                ? (provider.email ?? t('Connected'))
                                                : t('Not connected')}
                                        </p>
                                    </div>

                                    {provider.connected ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={processing}
                                            onClick={() =>
                                                router.delete(route('connected-accounts.destroy', { provider: provider.provider }), {
                                                    preserveScroll: true,
                                                    onStart: () => setProcessing(true),
                                                    onFinish: () => setProcessing(false),
                                                })
                                            }
                                        >
                                            {t('Disconnect')}
                                        </Button>
                                    ) : (
                                        <Button
                                            size="sm"
                                            disabled={processing}
                                            onClick={() =>
                                                router.post(
                                                    route('connected-accounts.connect', { provider: provider.provider }),
                                                    {},
                                                    { onStart: () => setProcessing(true) },
                                                )
                                            }
                                        >
                                            {t('Connect')}
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Said before a disconnect is tried, not after it is refused. */}
                    {!has_local_password && connectedCount === 1 && (
                        <Alert>
                            <AlertDescription>
                                {t(
                                    'Your account was created through one of these providers and has no password of its own, so the last connection cannot be removed. Set a password first if you want to disconnect it.',
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

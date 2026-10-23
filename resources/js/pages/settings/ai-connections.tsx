import { Head, router, usePage } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import PortalLayout from '@/layouts/portal-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

interface Connection {
    id: number;
    client_name: string;
    identity: string;
    verified: boolean;
    access: string;
    connected_at: string;
    last_used_at: string | null;
}

interface AiConnectionsProps {
    connections: Connection[];
    docs_url: string;
}

/**
 * Settings → AI connections (design §8.1): each assistant connected to
 * this account, what it may do, and the button that cuts it.
 */
export default function AiConnections({ connections, docs_url }: AiConnectionsProps) {
    const { t } = useTranslation();
    const { date } = useFormatDate();
    const { auth } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('AI connections'), href: '/settings/ai-connections' }];

    const accessLabel = (access: string) => {
        switch (access) {
            case 'Read':
                return t('Read');
            case 'Operate':
                return t('Operate');
            default:
                return t('Your own files');
        }
    };

    const content = (
        <div className="space-y-6">
            <HeadingSmall
                title={t('AI connections')}
                description={t('Assistants you connected to this account. Everything they do appears in your activity as \'AI assistant\'.')}
            />

            {connections.length === 0 ? (
                <div className="text-muted-foreground rounded-lg border border-dashed p-6 text-sm">
                    <p>{t('No assistant is connected to your account.')}</p>
                    <p className="mt-1">
                        <a href={docs_url} className="underline underline-offset-4" target="_blank" rel="noreferrer">
                            {t('How to connect one')}
                        </a>
                    </p>
                </div>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {connections.map((connection) => (
                        <li key={connection.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div className="min-w-0 space-y-1">
                                <p className="flex items-center gap-2 font-medium">
                                    <span className="truncate">{connection.client_name}</span>
                                    {connection.verified ? (
                                        <Badge variant="secondary">{t('Verified')}</Badge>
                                    ) : (
                                        <Badge variant="outline">{t('Unverified')}</Badge>
                                    )}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {connection.identity} · {accessLabel(connection.access)}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {t('Connected :date', { date: date(connection.connected_at) })}
                                    {connection.last_used_at
                                        ? ` · ${t('Last used :date', { date: date(connection.last_used_at) })}`
                                        : ''}
                                </p>
                            </div>

                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline" size="sm">
                                        {t('Disconnect')}
                                    </Button>
                                }
                                title={t('Disconnect :name?', { name: connection.client_name })}
                                description={t('It stops working right away. You can connect it again from the assistant.')}
                                confirmLabel={t('Disconnect')}
                                onConfirm={() => router.delete(route('mcp.connections.destroy', connection.id), { preserveScroll: true })}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );

    if (auth.user.type === 'client') {
        return (
            <PortalLayout>
                <Head title={t('AI connections')} />
                <div className="mx-auto w-full max-w-2xl px-4 py-6">{content}</div>
            </PortalLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('AI connections')} />
            <SettingsLayout>{content}</SettingsLayout>
        </AppLayout>
    );
}

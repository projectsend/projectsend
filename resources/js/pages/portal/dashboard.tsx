import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Bell, Download, Files, FileText, FolderKanban, HardDrive } from 'lucide-react';

import Heading from '@/components/heading';
import { StatTile } from '@/components/stat-tile';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/format-bytes';

interface LatestFile {
    id: number;
    name: string;
    size: number;
    created_at: string | null;
}

interface Storage {
    used_bytes: number;
    quota_bytes: number | null;
}

interface PortalDashboardProps {
    files_count: number;
    groups_count: number;
    storage: Storage;
    latest_files: LatestFile[];
}

export default function PortalDashboard({ files_count, groups_count, storage, latest_files }: PortalDashboardProps) {
    const { t } = useTranslation();
    const { auth, pending } = usePage<SharedData>().props;
    const { date } = useFormatDate();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Dashboard'), href: '/dashboard' }];

    const usagePercent =
        storage.quota_bytes !== null && storage.quota_bytes > 0 ? Math.min(100, Math.round((storage.used_bytes / storage.quota_bytes) * 100)) : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Dashboard')} />

            <div className="space-y-6 px-4 py-6">
                <Heading title={t('Hello, :name', { name: auth.user.name })} description={t('Here is what has been shared with you')} />

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatTile
                        label={t('Files shared with you')}
                        value={String(files_count)}
                        href="/my-files"
                        icon={Files}
                        accentClassName="bg-chart-1/10 border-chart-1/25"
                        iconClassName="bg-chart-1/20 text-chart-1"
                    />
                    <StatTile
                        label={t('Storage used')}
                        value={formatBytes(storage.used_bytes)}
                        hint={storage.quota_bytes !== null ? t('of :quota', { quota: formatBytes(storage.quota_bytes) }) : t('Unlimited storage')}
                        progress={storage.quota_bytes !== null ? usagePercent : undefined}
                        href="/my-files/upload"
                        icon={HardDrive}
                        accentClassName="bg-primary/10 border-primary/25"
                        iconClassName="bg-primary/20 text-primary"
                    />
                    <StatTile
                        label={t('Your groups')}
                        value={String(groups_count)}
                        href="/my-groups"
                        icon={FolderKanban}
                        accentClassName="bg-chart-2/10 border-chart-2/25"
                        iconClassName="bg-chart-2/20 text-chart-2"
                    />
                    <StatTile
                        label={t('Notifications')}
                        value={String(pending.notifications_unread ?? 0)}
                        icon={Bell}
                        accentClassName="bg-chart-5/10 border-chart-5/25"
                        iconClassName="bg-chart-5/20 text-chart-5"
                    />
                </div>

                {latest_files.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-sm font-semibold">{t('Latest files')}</h2>
                        {latest_files.map((file) => (
                            <div key={file.id} className="bg-card flex items-center justify-between gap-4 rounded-lg border px-4 py-3">
                                <div className="flex min-w-0 items-center gap-3">
                                    <FileText className="text-primary size-6 shrink-0" strokeWidth={1.5} />
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">{file.name}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {formatBytes(file.size)} · {date(file.created_at)}
                                        </p>
                                    </div>
                                </div>
                                <Button size="sm" variant="outline" asChild>
                                    <a href={route('files.download', file.id)}>
                                        <Download className="size-4" />
                                        <span className="sr-only">{t('Download')}</span>
                                    </a>
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

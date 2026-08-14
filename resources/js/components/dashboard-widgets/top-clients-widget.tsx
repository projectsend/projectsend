import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/format-bytes';

export interface TopClient {
    id: number;
    name: string;
    used_bytes: number;
    quota_mb: number;
}

export function TopClientsWidget({ topClients }: { topClients: TopClient[] }) {
    const { t } = useTranslation();

    return (
        <table className="w-full table-fixed text-sm">
            <colgroup>
                <col className="w-auto" />
                <col className="w-16" />
                <col className="w-32" />
            </colgroup>
            <thead>
                <tr className="text-muted-foreground text-xs">
                    <th className="pb-2 text-left font-medium">{t('Client')}</th>
                    <th className="pb-2 pl-4 text-right font-medium">{t('Used')}</th>
                    <th className="pb-2 pl-4 text-left font-medium">{t('Quota')}</th>
                </tr>
            </thead>
            <tbody>
                {topClients.map((client) => {
                    const hasQuota = client.quota_mb > 0;
                    const usagePercent = hasQuota ? Math.min(100, Math.round((client.used_bytes / (client.quota_mb * 1024 * 1024)) * 100)) : 0;

                    return (
                        <tr key={client.id} className="border-t">
                            <td className="max-w-0 truncate py-2 pr-4 font-medium">{client.name}</td>
                            <td className="py-2 pl-4 text-right whitespace-nowrap tabular-nums">{formatBytes(client.used_bytes)}</td>
                            <td className="py-2 pr-1 pl-4">
                                {hasQuota ? (
                                    <div className="flex items-center gap-1.5">
                                        <div className="bg-muted h-1.5 w-10 shrink-0 overflow-hidden rounded-full">
                                            <div
                                                className={`h-full rounded-full ${usagePercent >= 90 ? 'bg-destructive' : 'bg-primary'}`}
                                                style={{ width: `${usagePercent}%` }}
                                            />
                                        </div>
                                        <span className="text-muted-foreground shrink-0 text-xs whitespace-nowrap">
                                            {formatBytes(client.quota_mb * 1024 * 1024)}
                                        </span>
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground text-xs">{t('Unlimited')}</span>
                                )}
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

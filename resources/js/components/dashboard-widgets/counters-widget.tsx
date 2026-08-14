import { Files, FolderKanban, HardDrive, UserCog, Users } from 'lucide-react';

import { StatTile } from '@/components/stat-tile';
import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/format-bytes';

export interface Counters {
    files: number;
    files_bytes: number;
    clients: number;
    groups: number;
    users: number;
}

export function CountersWidget({ counters }: { counters: Counters }) {
    const { t } = useTranslation();

    return (
        // Container queries, not viewport breakpoints — this grid's real
        // constraint is the dashboard column's width (which shrinks as
        // the admin picks more columns), not the browser window's. A
        // narrow column falls back to one stat per row instead of
        // cramming tiles that no longer fit.
        <div className="@container">
            <div className="grid grid-cols-1 gap-4 @sm:grid-cols-2 @xl:grid-cols-3 @6xl:grid-cols-5">
                <StatTile
                    label={t('Files')}
                    value={String(counters.files)}
                    icon={Files}
                    accentClassName="bg-chart-1/10 border-chart-1/25"
                    iconClassName="bg-chart-1/20 text-chart-1"
                />
                <StatTile
                    label={t('Total size')}
                    value={formatBytes(counters.files_bytes)}
                    icon={HardDrive}
                    accentClassName="bg-primary/10 border-primary/25"
                    iconClassName="bg-primary/20 text-primary"
                />
                <StatTile
                    label={t('Clients')}
                    value={String(counters.clients)}
                    icon={Users}
                    accentClassName="bg-chart-2/10 border-chart-2/25"
                    iconClassName="bg-chart-2/20 text-chart-2"
                />
                <StatTile
                    label={t('Groups')}
                    value={String(counters.groups)}
                    icon={FolderKanban}
                    accentClassName="bg-chart-4/10 border-chart-4/25"
                    iconClassName="bg-chart-4/20 text-chart-4"
                />
                <StatTile
                    label={t('System users')}
                    value={String(counters.users)}
                    icon={UserCog}
                    accentClassName="bg-chart-5/10 border-chart-5/25"
                    iconClassName="bg-chart-5/20 text-chart-5"
                />
            </div>
        </div>
    );
}

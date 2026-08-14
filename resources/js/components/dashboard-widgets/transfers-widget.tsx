import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAppliedTheme } from '@/hooks/use-applied-theme';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

export interface TransferPoint {
    date: string;
    uploads: number;
    downloads_clients: number;
    downloads_anonymous: number;
}

export interface TransfersRange {
    preset: 'last_week' | 'last_month' | 'previous_month' | 'custom';
    from: string;
    to: string;
}

// Categorical palette validated with the dataviz six-checks script
// against the card surfaces of each mode.
const SERIES_COLORS = {
    light: { uploads: '#6247d1', downloadsClients: '#0e8a5f', downloadsAnonymous: '#d97706' },
    dark: { uploads: '#7c5ae2', downloadsClients: '#199e70', downloadsAnonymous: '#fbbf24' },
};

export function TransfersRangeControls({
    transfersRange,
    onRangeChange,
}: {
    transfersRange: TransfersRange | null;
    onRangeChange: (params: Record<string, string>) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Select
                value={transfersRange?.preset ?? 'last_month'}
                onValueChange={(preset) =>
                    onRangeChange(
                        preset === 'custom' ? { range: 'custom', from: transfersRange?.from ?? '', to: transfersRange?.to ?? '' } : { range: preset },
                    )
                }
            >
                <SelectTrigger className="h-8 w-40 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="last_week">{t('Last week')}</SelectItem>
                    <SelectItem value="last_month">{t('Last month')}</SelectItem>
                    <SelectItem value="previous_month">{t('Previous month')}</SelectItem>
                    <SelectItem value="custom">{t('Custom range')}</SelectItem>
                </SelectContent>
            </Select>
            {transfersRange?.preset === 'custom' && (
                <>
                    <Input
                        type="date"
                        aria-label={t('From')}
                        className="h-8 w-36 text-xs"
                        value={transfersRange.from}
                        onChange={(e) => onRangeChange({ range: 'custom', from: e.target.value, to: transfersRange.to })}
                    />
                    <span className="text-muted-foreground text-xs">{t('to')}</span>
                    <Input
                        type="date"
                        aria-label={t('To')}
                        className="h-8 w-36 text-xs"
                        value={transfersRange.to}
                        onChange={(e) => onRangeChange({ range: 'custom', from: transfersRange.from, to: e.target.value })}
                    />
                </>
            )}
        </div>
    );
}

export function TransfersWidget({ transfers }: { transfers: TransferPoint[] }) {
    const { t } = useTranslation();
    const { calendarDateShort } = useFormatDate();
    const theme = useAppliedTheme();
    const colors = SERIES_COLORS[theme];

    // Thins out X-axis labels so long custom ranges don't overlap, while
    // short ones (e.g. last week) still label most/every point.
    const tickInterval = Math.max(0, Math.floor(transfers.length / 6));

    return (
        <div>
            <div className="mb-3 flex flex-wrap items-center gap-4 text-xs">
                <span className="flex items-center gap-1.5">
                    <span className="size-2.5 rounded-full" style={{ backgroundColor: colors.uploads }} />
                    <span className="text-muted-foreground">{t('Uploads')}</span>
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="size-2.5 rounded-full" style={{ backgroundColor: colors.downloadsClients }} />
                    <span className="text-muted-foreground">{t('Client downloads')}</span>
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="size-2.5 rounded-full" style={{ backgroundColor: colors.downloadsAnonymous }} />
                    <span className="text-muted-foreground">{t('Anonymous downloads')}</span>
                </span>
            </div>

            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={transfers} margin={{ top: 12, right: 12, bottom: 0, left: -20 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" />
                        <XAxis
                            dataKey="date"
                            tickFormatter={calendarDateShort}
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            interval={tickInterval}
                        />
                        <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }} tickLine={false} axisLine={false} />
                        <Tooltip
                            cursor={{ stroke: 'var(--border)' }}
                            labelFormatter={(value) => calendarDateShort(String(value))}
                            contentStyle={{
                                backgroundColor: 'var(--card)',
                                border: '1px solid var(--border)',
                                borderRadius: 'var(--radius)',
                                fontSize: 12,
                                color: 'var(--foreground)',
                            }}
                        />
                        <Line
                            type="monotone"
                            dataKey="uploads"
                            name={t('Uploads')}
                            stroke={colors.uploads}
                            strokeWidth={2}
                            dot={false}
                            activeDot={{ r: 4 }}
                        />
                        <Line
                            type="monotone"
                            dataKey="downloads_clients"
                            name={t('Client downloads')}
                            stroke={colors.downloadsClients}
                            strokeWidth={2}
                            dot={false}
                            activeDot={{ r: 4 }}
                        />
                        <Line
                            type="monotone"
                            dataKey="downloads_anonymous"
                            name={t('Anonymous downloads')}
                            stroke={colors.downloadsAnonymous}
                            strokeWidth={2}
                            dot={false}
                            activeDot={{ r: 4 }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

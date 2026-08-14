import { Head, Link, router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAppliedTheme } from '@/hooks/use-applied-theme';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface Summary {
    tokens: number;
    tokens_expired: number;
    requests_7d: number;
    failed_7d: number;
    median_ms: number | null;
}

interface DailyPoint {
    date: string;
    requests: number;
    failed: number;
}

interface TokenUsage {
    id: string;
    name: string;
    owner: string | null;
    abilities: string[];
    last_used_at: string | null;
    expires_at: string | null;
    expired: boolean;
    requests_7d: number;
    failed_7d: number;
}

interface RecentAction {
    id: number;
    created_at: string;
    actor_name: string | null;
    token_name: string | null;
    template: string;
    replacements: Record<string, string>;
}

interface Props {
    summary: Summary;
    daily: DailyPoint[];
    tokens: TokenUsage[];
    recent_actions: RecentAction[];
    top_endpoints: { route: string; method: string; requests: number }[];
    scope: { install_wide: boolean; can_view_install_wide: boolean };
    retention_days: number;
}

// Categorical pair validated against the card surfaces of both modes, the
// same approach as the transfers widget.
// Succeeded and failed are stacked rather than drawn as two independent
// series, because failures are a subset of requests — plotting both totals
// would count every failure twice and make a bad day look busy.
const SERIES = {
    light: { succeeded: '#6247d1', failed: '#dc2626' },
    dark: { succeeded: '#7c5ae2', failed: '#f87171' },
};

function StatTile({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-muted-foreground text-xs font-medium">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
            {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
        </div>
    );
}

export default function ApiDashboard({ summary, daily, tokens, recent_actions, top_endpoints, scope, retention_days }: Props) {
    const { t } = useTranslation();
    // The chart's x-axis is a day key (a bare YYYY-MM-DD), not an
    // instant — see useFormatDate on why the two take different
    // functions.
    const { date, dateTime, calendarDateShort } = useFormatDate();
    const theme = useAppliedTheme();
    const colors = theme === 'dark' ? SERIES.dark : SERIES.light;

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('API'), href: '/api' }];

    const toggleScope = () => {
        router.get('/api', scope.install_wide ? {} : { all: '1' }, { preserveScroll: true });
    };

    // Failures are a subset of the total, so the stacked pair has to be
    // (total - failed, failed) rather than (total, failed).
    const series = daily.map((point) => ({
        date: point.date,
        succeeded: point.requests - point.failed,
        failed: point.failed,
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('API')} />

            <div className="space-y-8 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('API')}
                        description={
                            scope.install_wide
                                ? t('Every token on this installation and what it has been doing.')
                                : t('Your tokens and what they have been doing.')
                        }
                    />
                    <div className="flex items-center gap-2">
                        {scope.can_view_install_wide && (
                            <Button variant="outline" size="sm" onClick={toggleScope}>
                                {scope.install_wide ? t('Show only mine') : t('Show all tokens')}
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('api.docs')}>{t('Documentation')}</Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href={route('api-tokens.index')}>{t('Manage tokens')}</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile
                        label={t('Tokens')}
                        value={String(summary.tokens)}
                        hint={summary.tokens_expired > 0 ? t(':count expired', { count: summary.tokens_expired }) : undefined}
                    />
                    <StatTile label={t('Requests (7 days)')} value={String(summary.requests_7d)} />
                    <StatTile
                        label={t('Failed (7 days)')}
                        value={String(summary.failed_7d)}
                        hint={summary.requests_7d > 0 ? `${Math.round((summary.failed_7d / summary.requests_7d) * 100)}%` : undefined}
                    />
                    <StatTile
                        label={t('Median response')}
                        value={summary.median_ms === null ? '—' : `${summary.median_ms} ms`}
                        hint={summary.median_ms === null ? t('No requests yet') : undefined}
                    />
                </div>

                <section>
                    <h2 className="mb-3 text-base font-semibold">{t('Requests')}</h2>
                    <div className="rounded-lg border p-4">
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={series} margin={{ top: 4, right: 8, left: -20, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                                <XAxis
                                    dataKey="date"
                                    tick={{ fontSize: 11 }}
                                    tickLine={false}
                                    axisLine={false}
                                    minTickGap={24}
                                    tickFormatter={(value) => calendarDateShort(String(value))}
                                />
                                <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} allowDecimals={false} />
                                <Tooltip
                                    cursor={{ fillOpacity: 0.08 }}
                                    contentStyle={{ fontSize: 12, borderRadius: 6 }}
                                    labelFormatter={(value) => calendarDateShort(String(value))}
                                />
                                <Legend wrapperStyle={{ fontSize: 12 }} />
                                <Bar dataKey="succeeded" stackId="requests" name={t('Succeeded')} fill={colors.succeeded} />
                                <Bar dataKey="failed" stackId="requests" name={t('Failed')} fill={colors.failed} />
                            </BarChart>
                        </ResponsiveContainer>
                        <p className="text-muted-foreground mt-2 text-xs">
                            {retention_days > 0
                                ? t('Request history is kept for :days days.', { days: retention_days })
                                : t('Request history is kept indefinitely.')}
                        </p>
                    </div>
                </section>

                <section className="grid gap-8 lg:grid-cols-2">
                    <div>
                        <h2 className="mb-3 text-base font-semibold">{t('Tokens')}</h2>
                        {tokens.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('No tokens yet.')}</p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">{t('Name')}</th>
                                            {scope.install_wide && <th className="px-4 py-2 font-medium">{t('Owner')}</th>}
                                            <th className="px-4 py-2 font-medium">{t('Last used')}</th>
                                            <th className="px-4 py-2 text-right font-medium">{t('Requests')}</th>
                                            <th className="px-4 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tokens.map((token) => (
                                            <tr key={token.id} className="border-t">
                                                <td className="px-4 py-2">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium">{token.name}</span>
                                                        {token.expired && <Badge variant="destructive">{t('Expired')}</Badge>}
                                                    </div>
                                                </td>
                                                {scope.install_wide && <td className="text-muted-foreground px-4 py-2">{token.owner}</td>}
                                                <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                                                    {token.last_used_at ? date(token.last_used_at) : t('Never')}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {token.requests_7d}
                                                    {token.failed_7d > 0 && (
                                                        <span className="text-destructive ml-1 text-xs">({token.failed_7d})</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/activity?origin=api&api_token=${encodeURIComponent(token.name)}`}>
                                                            {t('History')}
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div>
                        <h2 className="mb-3 text-base font-semibold">{t('Busiest endpoints')}</h2>
                        {top_endpoints.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('No requests in the last 7 days.')}</p>
                        ) : (
                            <ul className="divide-border divide-y rounded-lg border">
                                {top_endpoints.map((endpoint) => (
                                    <li key={`${endpoint.method} ${endpoint.route}`} className="flex items-center justify-between gap-4 px-4 py-2">
                                        <span className="font-mono text-xs">
                                            <span className="text-muted-foreground">{endpoint.method}</span> /{endpoint.route}
                                        </span>
                                        <span className="text-sm tabular-nums">{endpoint.requests}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-semibold">{t('Recent changes made through the API')}</h2>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/activity?origin=api">{t('Full history')}</Link>
                        </Button>
                    </div>
                    {recent_actions.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('Nothing has been changed through the API yet.')}</p>
                    ) : (
                        <ul className="divide-border divide-y rounded-lg border">
                            {recent_actions.map((action) => (
                                <li key={action.id} className="flex flex-wrap items-baseline justify-between gap-2 px-4 py-2">
                                    <span className="text-sm">{t(action.template, action.replacements)}</span>
                                    <span className="text-muted-foreground text-xs">
                                        {action.token_name && <span className="font-mono">{action.token_name}</span>}
                                        {action.token_name && ' · '}
                                        {dateTime(action.created_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

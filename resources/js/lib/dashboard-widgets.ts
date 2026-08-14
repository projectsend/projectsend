/**
 * Every dashboard widget key DashboardController knows how to render —
 * kept as a single ordered list so the Widgets modal has a stable
 * checkbox order regardless of the viewer's current layout. Actual
 * per-key props differ too much (transfers needs range controls, system
 * needs the release dialog state, …) for a generic component registry —
 * dashboard.tsx renders each one explicitly instead.
 */
export const WIDGET_KEYS = [
    'counters',
    'transfers',
    'top_clients_by_storage',
    'largest_files',
    'recent',
    'system',
    'news',
    'expired_files',
    'api',
] as const;

export type WidgetKey = (typeof WIDGET_KEYS)[number];

export const WIDGET_LABELS: Record<WidgetKey, string> = {
    counters: 'Counters',
    transfers: 'Transfers',
    top_clients_by_storage: 'Top clients by storage',
    largest_files: 'Largest files',
    recent: 'Recent activity',
    system: 'System',
    news: 'News',
    expired_files: 'Expired files',
    api: 'API usage',
};

export interface WidgetLayoutEntry {
    enabled: boolean;
    column_index: number;
    position: number;
}

export type WidgetLayout = Partial<Record<WidgetKey, WidgetLayoutEntry>>;

import { Link } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

export interface ApiUsageSummary {
    requests_7d: number;
    tokens: number;
    expired_tokens: number;
    last_used_at: string | null;
}

/**
 * The viewer's own API usage at a glance. Scoped to their own tokens, like
 * the API dashboard it links to — a widget everyone sees must not become a
 * window onto colleagues' integrations.
 */
export function ApiWidget({ summary }: { summary: ApiUsageSummary }) {
    const { t } = useTranslation();

    const { date } = useFormatDate();

    if (summary.tokens === 0) {
        return (
            <div className="space-y-3">
                <p className="text-muted-foreground text-sm">{t('You have no API tokens. Create one to let an external tool act on your behalf.')}</p>
                <Button variant="outline" size="sm" asChild>
                    <Link href="/settings/api-tokens/create">{t('Create token')}</Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <p className="text-muted-foreground text-xs font-medium">{t('Requests (7 days)')}</p>
                    <p className="mt-1 text-2xl font-semibold tabular-nums">{summary.requests_7d}</p>
                </div>
                <div>
                    <p className="text-muted-foreground text-xs font-medium">{t('Tokens')}</p>
                    <p className="mt-1 flex items-center gap-2 text-2xl font-semibold tabular-nums">
                        {summary.tokens}
                        {summary.expired_tokens > 0 && (
                            <Badge variant="destructive" className="text-xs">
                                {t(':count expired', { count: summary.expired_tokens })}
                            </Badge>
                        )}
                    </p>
                </div>
            </div>

            <p className="text-muted-foreground text-xs">
                {summary.last_used_at ? t('Last used :date', { date: date(summary.last_used_at) }) : t('Never used')}
            </p>

            <Button variant="ghost" size="sm" asChild className="px-0">
                <Link href="/api">{t('Open the API dashboard')}</Link>
            </Button>
        </div>
    );
}

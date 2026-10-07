import { Link, router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

export interface PaginationMeta {
    page: number;
    last_page: number;
    prev: string | null;
    next: string | null;
    total: number;
}

/**
 * Prev/Next pager for a server-paginated list, plus a page-select dropdown
 * to jump straight to a given page. Renders nothing for a single page. A
 * missing neighbour URL is a truly disabled button (not a dead "#" link),
 * and paging preserves scroll so the toolbar stays put.
 *
 * The jump-to-page target is built from the current URL's own query
 * string (just swapping `page`), rather than from meta.prev/next, since
 * every controller emitting this shape already follows Laravel's
 * `?page=N` convention and this way the control works even from page 1
 * (where meta.prev is null).
 */
export function Pagination({ meta }: { meta: PaginationMeta }) {
    const { t } = useTranslation();

    if (meta.last_page <= 1) return null;

    const goToPage = (page: number) => {
        if (page === meta.page) return;

        const params = new URLSearchParams(window.location.search);
        params.set('page', String(page));
        const query = params.toString();

        router.visit(`${window.location.pathname}${query ? `?${query}` : ''}`, { preserveScroll: true });
    };

    const pages = Array.from({ length: meta.last_page }, (_, i) => i + 1);

    return (
        <div className="mt-4 flex items-center justify-between gap-4">
            <p className="text-muted-foreground text-sm">{t(':total total', { total: meta.total })}</p>
            <div className="flex items-center gap-2">
                {meta.prev ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={meta.prev} preserveScroll>
                            {t('Previous')}
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        {t('Previous')}
                    </Button>
                )}

                <div className="flex items-center gap-1.5">
                    <span className="text-muted-foreground text-sm">{t('Page')}</span>
                    <Select value={String(meta.page)} onValueChange={(v) => goToPage(Number(v))}>
                        <SelectTrigger className="h-8 w-[4.5rem]" aria-label={t('Page')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {pages.map((page) => (
                                <SelectItem key={page} value={String(page)}>
                                    {page}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span className="text-muted-foreground text-sm">{t('of :count', { count: meta.last_page })}</span>
                </div>

                {meta.next ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={meta.next} preserveScroll>
                            {t('Next')}
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        {t('Next')}
                    </Button>
                )}
            </div>
        </div>
    );
}

/**
 * The same pager for a list that is already in the browser: Previous, a
 * page select and Next, beside which rows are showing. Renders nothing for
 * a single page.
 */
export function ListPager({ page, pageSize, total, onPage }: { page: number; pageSize: number; total: number; onPage: (page: number) => void }) {
    const { t } = useTranslation();
    const lastPage = Math.ceil(total / pageSize);

    if (lastPage <= 1) return null;

    const pages = Array.from({ length: lastPage }, (_, i) => i + 1);

    return (
        <nav aria-label={t('Pages')} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <p className="text-muted-foreground text-sm tabular-nums">
                {t('Showing :from–:to of :total', { from: (page - 1) * pageSize + 1, to: Math.min(page * pageSize, total), total })}
            </p>
            <div className="flex items-center gap-2">
                {/* aria-disabled, so reaching the first or last page does not
                    drop focus from the button that got there. */}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="aria-disabled:pointer-events-none aria-disabled:opacity-50"
                    aria-disabled={page === 1}
                    onClick={() => page > 1 && onPage(page - 1)}
                >
                    {t('Previous')}
                </Button>
                <div className="flex items-center gap-1.5">
                    <span className="text-muted-foreground text-sm">{t('Page')}</span>
                    <Select value={String(page)} onValueChange={(v) => onPage(Number(v))}>
                        <SelectTrigger className="h-8 w-[4.5rem]" aria-label={t('Page')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {pages.map((p) => (
                                <SelectItem key={p} value={String(p)}>
                                    {p}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span className="text-muted-foreground text-sm">{t('of :count', { count: lastPage })}</span>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="aria-disabled:pointer-events-none aria-disabled:opacity-50"
                    aria-disabled={page === lastPage}
                    onClick={() => page < lastPage && onPage(page + 1)}
                >
                    {t('Next')}
                </Button>
            </div>
        </nav>
    );
}

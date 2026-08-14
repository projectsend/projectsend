import { Check, ChevronRight, Copy, ExternalLink, Loader2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { CommentThread } from '@/components/comments/comment-thread';
import { DownloadAction } from '@/components/download-action';
import { type VersionLinks } from '@/components/files/version-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import { activityActorLabel as actorLabel } from '@/lib/activity-actor';
import { categoryColor } from '@/lib/category-colors';
import { formatBytes } from '@/lib/format-bytes';
import { type DownloadLimit } from '@/types/portal';

export interface DetailsTarget {
    type: 'file' | 'folder';
    id: number;
    /**
     * Which tab to open on. Carried on the target rather than passed
     * separately so opening the panel *at* something — a row's comment
     * icon, a notification's deep link — is one state change, and cannot
     * get out of step with which row is being shown.
     */
    tab?: Tab;
}

interface Named {
    id: number;
    name: string;
}

interface CategoryTag {
    id: number;
    name: string;
    color: string;
}

interface Details {
    type: 'file' | 'folder';
    id: number;
    name: string;
    description?: string | null;
    original_name?: string;
    size?: number;
    mime_type?: string;
    checksum?: string;
    uploader?: string | null;
    creator?: string | null;
    categories?: CategoryTag[];
    folder?: { id: number; name: string } | null;
    files_count?: number;
    children_count?: number;
    created_at: string | null;
    expires_at?: string | null;
    expired?: boolean;
    /** Null when the file may be downloaded any number of times. */
    download_limit?: number | null;
    download_limit_scope?: 'total' | 'per_user';
    /** The file's own total downloads, whatever the scope. */
    downloads_used?: number;
    /** What this viewer has left — what the button below obeys. */
    download_allowance?: DownloadLimit;
    /** Already narrowed to what this viewer may be told. */
    version?: VersionLinks;
    /** Set when this file is a revision: where its recipients actually live. */
    sharing_root?: { id: number; name: string } | null;
    download_url?: string;
    edit_url?: string;
    open_url?: string;
    can_update: boolean;
    can_view_activity: boolean;
    comments_enabled?: boolean;
    shares: {
        clients: Named[];
        groups: Named[];
    };
    share_links?: ShareLink[];
}

interface ShareLink {
    id: number;
    url: string;
    expires_at: string | null;
    max_downloads: number | null;
    downloads_count: number;
}

interface ActivityEntry {
    id: number;
    created_at: string;
    actor_name: string | null;
    actor_type: string | null;
    /** Separates an unauthenticated visitor from the scheduler; both have no actor. */
    origin: string;
    template: string;
    replacements: Record<string, string>;
}

interface FileDownload {
    created_at: string;
    ip_address: string | null;
}

interface FileDownloader {
    actor_id: number | null;
    actor_name: string;
    actor_type: string | null;
    count: number;
    downloads: FileDownload[];
}

export type Tab = 'details' | 'sharing' | 'comments' | 'activity' | 'downloads';

export function DetailsPanel({ target, onClose }: { target: DetailsTarget; onClose: () => void }) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>('details');
    const [details, setDetails] = useState<Details | null>(null);
    const [activity, setActivity] = useState<ActivityEntry[] | null>(null);
    const [activityTotal, setActivityTotal] = useState(0);
    const [downloaders, setDownloaders] = useState<FileDownloader[] | null>(null);
    const [downloadsTotal, setDownloadsTotal] = useState(0);
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});
    const [copiedLinkId, setCopiedLinkId] = useState<number | null>(null);

    const base = target.type === 'file' ? `/files/${target.id}` : `/folders/${target.id}`;

    const loadDetails = () => {
        fetch(`${base}/details`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then(setDetails);
    };

    useEffect(() => {
        setDetails(null);
        setActivity(null);
        setActivityTotal(0);
        setDownloaders(null);
        setDownloadsTotal(0);
        setExpanded({});
        setTab(target.tab ?? 'details');
        loadDetails();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target.type, target.id, target.tab]);

    useEffect(() => {
        if (tab === 'activity' && activity === null && details?.can_view_activity) {
            fetch(`${base}/activity`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => r.json())
                .then((d) => {
                    setActivity(d.entries);
                    setActivityTotal(d.total);
                });
        }
        if (tab === 'downloads' && downloaders === null && details?.can_view_activity) {
            fetch(`${base}/downloads`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => r.json())
                .then((d) => {
                    setDownloaders(d.downloaders);
                    setDownloadsTotal(d.total);
                });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab, details]);

    const copyShareLink = (id: number, url: string) => {
        void navigator.clipboard.writeText(url);
        setCopiedLinkId(id);
        window.setTimeout(() => setCopiedLinkId((current) => (current === id ? null : current)), 1500);
    };

    const { date, dateTime } = useFormatDate();

    return (
        <>
            <div className="fixed inset-0 z-40 bg-black/20" onClick={onClose} aria-hidden />
            <aside className="bg-background fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l shadow-xl">
                <header className="flex items-center justify-between border-b px-4 py-3">
                    <p className="truncate pr-2 font-semibold">{details?.name ?? t('Details')}</p>
                    <Button variant="ghost" size="icon" onClick={onClose}>
                        <X className="size-4" />
                        <span className="sr-only">{t('Close')}</span>
                    </Button>
                </header>

                <nav className="flex gap-1 border-b px-2">
                    {(['details', 'sharing', 'comments', 'activity', 'downloads'] as Tab[])
                        .filter((tabKey) => tabKey !== 'comments' || (details?.type === 'file' && details?.comments_enabled))
                        .filter((tabKey) => tabKey !== 'activity' || details?.can_view_activity)
                        .filter((tabKey) => tabKey !== 'downloads' || (details?.type === 'file' && details?.can_view_activity))
                        .map((tabKey) => (
                            <button
                                key={tabKey}
                                onClick={() => setTab(tabKey)}
                                className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                            >
                                {tabKey === 'details'
                                    ? t('Details')
                                    : tabKey === 'sharing'
                                      ? t('Sharing')
                                      : tabKey === 'comments'
                                        ? t('Comments')
                                        : tabKey === 'activity'
                                          ? t('Activity')
                                          : t('Downloads')}
                            </button>
                        ))}
                </nav>

                <div className="flex-1 overflow-y-auto p-4 text-sm">
                    {details === null ? (
                        <div className="text-muted-foreground flex items-center gap-2 py-8">
                            <Loader2 className="size-4 animate-spin" /> {t('Loading…')}
                        </div>
                    ) : tab === 'details' ? (
                        <dl className="space-y-3">
                            {details.type === 'file' && (
                                <>
                                    <Row label={t('File name')} value={details.original_name ?? ''} />
                                    <Row label={t('Size')} value={formatBytes(details.size ?? 0)} />
                                    <Row label={t('Type')} value={details.mime_type ?? ''} />
                                    <Row label={t('Folder')} value={details.folder?.name ?? t('No folder')} />
                                    <Row label={t('Uploaded by')} value={details.uploader ?? '—'} />
                                    {details.version?.previous && <Row label={t('New version of')} value={details.version.previous.name} />}
                                    {details.version?.next && <Row label={t('Replaced by')} value={details.version.next.name} />}
                                    <div className="grid gap-0.5">
                                        <dt className="text-muted-foreground text-xs">{t('Categories')}</dt>
                                        {details.categories && details.categories.length > 0 ? (
                                            <dd className="flex flex-wrap gap-1">
                                                {details.categories.map((category) => (
                                                    <Badge
                                                        key={category.id}
                                                        variant="outline"
                                                        className={`font-normal ${categoryColor(category.color).badge}`}
                                                    >
                                                        {category.name}
                                                    </Badge>
                                                ))}
                                            </dd>
                                        ) : (
                                            <dd>{t('None')}</dd>
                                        )}
                                    </div>
                                    {details.description && <Row label={t('Description')} value={details.description} />}
                                    <Row
                                        label={t('Expires')}
                                        value={
                                            details.expires_at
                                                ? details.expired
                                                    ? t('Expired :date', { date: date(details.expires_at) })
                                                    : date(details.expires_at)
                                                : t('Never')
                                        }
                                    />
                                    <Row label={t('Download limit')} value={downloadLimitSummary(details, t)} />
                                    <Row label={t('Checksum (SHA-256)')} value={details.checksum ?? ''} mono />
                                </>
                            )}
                            {details.type === 'folder' && (
                                <>
                                    <Row label={t('Files')} value={String(details.files_count ?? 0)} />
                                    <Row label={t('Subfolders')} value={String(details.children_count ?? 0)} />
                                    <Row label={t('Created by')} value={details.creator ?? '—'} />
                                </>
                            )}
                            <Row label={t('Created')} value={dateTime(details.created_at)} />

                            <div className="flex flex-wrap gap-2 pt-2">
                                {details.download_url && (
                                    <DownloadAction
                                        href={details.download_url}
                                        limit={details.download_allowance ?? { limit: null, left: null, blocked: false }}
                                    />
                                )}
                                {details.open_url && (
                                    <Button size="sm" variant="outline" asChild>
                                        <a href={details.open_url}>
                                            <ExternalLink className="size-4" /> {t('Open folder')}
                                        </a>
                                    </Button>
                                )}
                                {details.edit_url && details.can_update && (
                                    <Button size="sm" variant="outline" asChild>
                                        <a href={details.edit_url}>{t('Edit')}</a>
                                    </Button>
                                )}
                            </div>
                        </dl>
                    ) : tab === 'sharing' ? (
                        <div className="space-y-4">
                            {details.type === 'folder' && (
                                <p className="text-muted-foreground text-xs">
                                    {t('Sharing a folder shares everything inside it, now and later. Manage sharing from the edit page.')}
                                </p>
                            )}

                            {/* The list below is the chain root's, which is
                                what really governs access — say so, or it
                                reads as this file's own and looks
                                uneditable for no reason. */}
                            {details.sharing_root && (
                                <p className="text-muted-foreground text-xs">
                                    {t('This is a new version of ":name" and is shared with the same people. Manage sharing on that file.', {
                                        name: details.sharing_root.name,
                                    })}
                                </p>
                            )}

                            <div className="space-y-1">
                                {details.shares.clients.length === 0 && details.shares.groups.length === 0 && (
                                    <p className="text-muted-foreground py-4 text-center text-xs">{t('Not shared with anyone yet.')}</p>
                                )}
                                {details.shares.clients.map((c) => (
                                    <div key={`c-${c.id}`} className="flex items-center justify-between rounded-md border px-3 py-1.5">
                                        <span>{c.name}</span>
                                    </div>
                                ))}
                                {details.shares.groups.map((g) => (
                                    <div key={`g-${g.id}`} className="flex items-center justify-between rounded-md border px-3 py-1.5">
                                        <span className="flex items-center gap-1.5">
                                            {g.name} <Badge variant="secondary">{t('Group')}</Badge>
                                        </span>
                                    </div>
                                ))}
                            </div>

                            {details.type === 'file' && (
                                <div className="space-y-3 border-t pt-4">
                                    <div>
                                        <p className="text-sm font-medium">{t('Public links')}</p>
                                        <p className="text-muted-foreground text-xs">{t('Manage public links from the edit page.')}</p>
                                    </div>

                                    <div className="space-y-1">
                                        {(details.share_links?.length ?? 0) === 0 && (
                                            <p className="text-muted-foreground py-2 text-center text-xs">{t('No public links yet.')}</p>
                                        )}
                                        {details.share_links?.map((link) => (
                                            <div key={link.id} className="rounded-md border px-3 py-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="truncate font-mono text-xs">{link.url}</span>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7 shrink-0"
                                                        onClick={() => copyShareLink(link.id, link.url)}
                                                    >
                                                        {copiedLinkId === link.id ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                                                        <span className="sr-only">{t('Copy link')}</span>
                                                    </Button>
                                                </div>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {/* A link's expiry was set as a plain date and is stored as
                                                        the end of that day, so it is shown as a date — a time of
                                                        23:59 here only ever read as a detail nobody chose. */}
                                                    {link.expires_at ? t('Expires :date', { date: date(link.expires_at) }) : t('Never expires')}
                                                    {' · '}
                                                    {link.max_downloads !== null
                                                        ? t(':used / :limit downloads', { used: link.downloads_count, limit: link.max_downloads })
                                                        : t(':count downloads', { count: link.downloads_count })}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : tab === 'comments' ? (
                        // Staff UI is not themed, so this uses the shared
                        // thread directly — no shell. The same component
                        // the file's own page and every theme render, so
                        // none of them can drift on what a comment looks
                        // like or who may write one.
                        <CommentThread fileId={details.id} />
                    ) : tab === 'activity' ? (
                        <div className="space-y-3">
                            {activity === null ? (
                                <div className="text-muted-foreground flex items-center gap-2">
                                    <Loader2 className="size-4 animate-spin" /> {t('Loading…')}
                                </div>
                            ) : activity.length === 0 ? (
                                <p className="text-muted-foreground text-xs">{t('No activity recorded yet.')}</p>
                            ) : (
                                activity.map((e) => (
                                    <div key={e.id} className="border-b pb-2 last:border-0">
                                        <p>
                                            <span className="font-medium">{t(actorLabel(e).key)}</span>{' '}
                                            <span className="text-muted-foreground">{t(e.template, e.replacements)}</span>
                                        </p>
                                        <p className="text-muted-foreground text-xs">{dateTime(e.created_at)}</p>
                                    </div>
                                ))
                            )}
                            {activityTotal > 0 && (
                                <div className="pt-1 text-center">
                                    <Button variant="link" size="sm" asChild>
                                        <a href={`${base}/activity/history`}>{t('View full history (:count)', { count: activityTotal })}</a>
                                    </Button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {downloaders === null ? (
                                <div className="text-muted-foreground flex items-center gap-2">
                                    <Loader2 className="size-4 animate-spin" /> {t('Loading…')}
                                </div>
                            ) : downloaders.length === 0 ? (
                                <p className="text-muted-foreground text-xs">{t('No downloads recorded yet.')}</p>
                            ) : (
                                downloaders.map((d) => {
                                    const key = d.actor_id !== null ? `u${d.actor_id}` : `d${d.actor_name}`;
                                    const isOpen = expanded[key] ?? false;
                                    return (
                                        <Collapsible
                                            key={key}
                                            open={isOpen}
                                            onOpenChange={(open) => setExpanded((prev) => ({ ...prev, [key]: open }))}
                                            className="rounded-md border"
                                        >
                                            <CollapsibleTrigger className="flex w-full items-center justify-between px-3 py-2 text-left">
                                                <span className="flex items-center gap-1.5">
                                                    <ChevronRight className={`size-3.5 shrink-0 transition-transform ${isOpen ? 'rotate-90' : ''}`} />
                                                    <span className="font-medium">{d.actor_name}</span>
                                                    {d.actor_type && (
                                                        <Badge variant="secondary">{d.actor_type === 'client' ? t('Client') : t('Staff')}</Badge>
                                                    )}
                                                </span>
                                                <span className="text-muted-foreground text-xs">{t(':count downloads', { count: d.count })}</span>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent className="space-y-1 border-t px-3 py-2">
                                                {d.downloads.map((dl, i) => (
                                                    <div key={i} className="flex items-center justify-between text-xs">
                                                        <span>{dateTime(dl.created_at)}</span>
                                                        <span className="text-muted-foreground font-mono">{dl.ip_address ?? '—'}</span>
                                                    </div>
                                                ))}
                                            </CollapsibleContent>
                                        </Collapsible>
                                    );
                                })
                            )}
                            {downloadsTotal > 0 && (
                                <div className="pt-1 text-center">
                                    <Button variant="link" size="sm" asChild>
                                        <a href={`${base}/downloads/history`}>{t('View all downloads (:count)', { count: downloadsTotal })}</a>
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </aside>
        </>
    );
}

/**
 * The file's own limit, in words — not this viewer's remaining
 * allowance, which is a different number and belongs on the button.
 *
 * A per-user limit deliberately does not quote a "used" figure. The only
 * count available here is the file's total across everybody, and putting
 * that beside a per-person cap reads as "2 of your 3 spent" when it
 * means nothing of the sort.
 */
function downloadLimitSummary(details: Details, t: (key: string, replacements?: Record<string, string | number>) => string): string {
    if (details.download_limit == null) {
        return t('No limit');
    }

    if (details.download_limit_scope === 'per_user') {
        return t(':limit downloads per person', { limit: details.download_limit });
    }

    return t(':used of :limit downloads used', { used: details.downloads_used ?? 0, limit: details.download_limit });
}

function Row({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className={mono ? 'font-mono text-xs break-all' : ''}>{value}</dd>
        </div>
    );
}

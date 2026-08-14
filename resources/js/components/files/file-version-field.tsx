import { InheritedSharingNotice, type SharingRoot } from '@/components/files/inherited-sharing-notice';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { SearchablePicker, type PickerOption } from '@/components/searchable-picker';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { router } from '@inertiajs/react';
import { ArrowRight, History, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

export interface VersionLink {
    id: number;
    name: string;
    url: string | null;
}

export interface ChainEntry {
    id: number;
    name: string;
    url: string;
    is_current: boolean;
}

interface FileVersionFieldProps {
    version: { previous: VersionLink | null; next: VersionLink | null };
    chain: ChainEntry[];
    sharingRoot: SharingRoot | null;
    canUpdateRoot: boolean;
    candidatesUrl: string;
    previewUrl: string;
    storeUrl: string;
    destroyUrl: string;
    error?: string;
}

interface Candidate {
    id: number;
    name: string;
    original_name: string;
    created_at: string | null;
}

interface Preview {
    clients: string[];
    groups: string[];
    empty: boolean;
}

/**
 * The Versions tab: what this file replaces, what replaced it, and the
 * control for changing that.
 *
 * Rendered as a sibling of the General tab's `<form>`, never inside it —
 * that form is one big submit, and a button in here without an explicit
 * type would save the whole file alongside the link.
 */
export function FileVersionField({
    version,
    chain,
    sharingRoot,
    canUpdateRoot,
    candidatesUrl,
    previewUrl,
    storeUrl,
    destroyUrl,
    error,
}: FileVersionFieldProps) {
    const { t } = useTranslation();
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [selected, setSelected] = useState<number | null>(null);
    const [loading, setLoading] = useState(false);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [saving, setSaving] = useState(false);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const search = useCallback(
        (query: string) => {
            setLoading(true);

            fetch(`${candidatesUrl}?search=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : { files: [] }))
                .then((data: { files: Candidate[] }) => setCandidates(data.files))
                .catch(() => setCandidates([]))
                .finally(() => setLoading(false));
        },
        [candidatesUrl],
    );

    // A file library is unbounded, so the list is searched server-side
    // rather than shipped whole with the page.
    useEffect(() => {
        if (version.previous === null) search('');
    }, [search, version.previous]);

    const onSearchChange = (query: string) => {
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => search(query), 250);
    };

    const link = (previousFileId: number) => {
        setSaving(true);
        router.put(
            storeUrl,
            { previous_file_id: previousFileId },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setConfirming(false);
                    setSelected(null);
                },
            },
        );
    };

    /**
     * Linking moves this file's own recipients onto the original, which can
     * widen the audience of a file the user is not currently looking at.
     * Right outcome — dropping the rows would take access away from people
     * who already have it — but not one to perform silently.
     */
    const startLink = () => {
        if (selected === null) return;

        setSaving(true);

        fetch(`${previewUrl}?previous_file_id=${selected}`, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : { clients: [], groups: [], empty: true }))
            .then((data: Preview) => {
                setSaving(false);

                if (data.empty) {
                    link(selected);

                    return;
                }

                setPreview(data);
                setConfirming(true);
            })
            .catch(() => {
                setSaving(false);
                link(selected);
            });
    };

    const options: PickerOption[] = candidates.map((candidate) => ({
        id: candidate.id,
        label: candidate.name,
        hint: candidate.original_name,
    }));

    return (
        <div className="grid max-w-xl gap-6">
            <div className="grid gap-2">
                <HeadingSmall title={t('Versions')} description={t('Mark this file as a newer version of something you uploaded before.')} />
            </div>

            {version.next && (
                <Alert variant="warning">
                    <TriangleAlert className="size-4" />
                    <AlertTitle>{t('This version is outdated')}</AlertTitle>
                    <AlertDescription>
                        {version.next.url ? (
                            <a href={version.next.url} className="font-medium underline underline-offset-4">
                                {t('It was replaced by ":name".', { name: version.next.name })}
                            </a>
                        ) : (
                            t('It was replaced by ":name".', { name: version.next.name })
                        )}
                    </AlertDescription>
                </Alert>
            )}

            {version.previous ? (
                <div className="grid gap-3 rounded-lg border p-4">
                    <div className="flex items-start justify-between gap-4">
                        <div className="grid gap-1">
                            <span className="text-muted-foreground text-xs">{t('This file is a new version of')}</span>
                            {version.previous.url ? (
                                <a href={version.previous.url} className="font-medium underline underline-offset-4">
                                    {version.previous.name}
                                </a>
                            ) : (
                                <span className="font-medium">{version.previous.name}</span>
                            )}
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={saving}
                            onClick={() => router.delete(destroyUrl, { preserveScroll: true })}
                        >
                            {t('Remove link')}
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="grid gap-3">
                    <SearchablePicker
                        options={options}
                        value={selected}
                        onChange={setSelected}
                        onSearchChange={onSearchChange}
                        loading={loading}
                        searchPlaceholder={t('Search files…')}
                        emptyLabel={(query) =>
                            query === '' ? t('No other files are available to link to.') : t('No files match ":search".', { search: query })
                        }
                    />
                    <InputError message={error} />
                    <Button type="button" className="w-fit" disabled={selected === null || saving} onClick={startLink}>
                        {t('Mark as a new version')}
                    </Button>
                </div>
            )}

            {sharingRoot && <InheritedSharingNotice root={sharingRoot} canUpdateRoot={canUpdateRoot} />}

            {chain.length > 1 && (
                <div className="grid gap-2">
                    <span className="text-muted-foreground text-xs">{t('Version history')}</span>
                    <ol className="flex flex-wrap items-center gap-1 text-sm">
                        {chain.map((entry, index) => (
                            <li key={entry.id} className="flex items-center gap-1">
                                {index > 0 && <ArrowRight className="text-muted-foreground size-3.5" />}
                                {entry.is_current ? (
                                    <span className="inline-flex items-center gap-1 font-medium">
                                        <History className="size-3.5" />
                                        {entry.name}
                                    </span>
                                ) : (
                                    <a href={entry.url} className="text-muted-foreground underline underline-offset-4">
                                        {entry.name}
                                    </a>
                                )}
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t("Share this file's recipients with the original?")}</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm">
                        {t(
                            'A new version is shared with the same people as the original. These recipients of this file will be added to it, so nobody loses access:',
                        )}
                    </p>
                    <ul className="grid gap-1 text-sm">
                        {preview?.clients.map((name) => <li key={`client-${name}`}>{name}</li>)}
                        {preview?.groups.map((name) => <li key={`group-${name}`}>{t(':name (group)', { name })}</li>)}
                    </ul>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => setConfirming(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button type="button" disabled={saving} onClick={() => selected !== null && link(selected)}>
                            {t('Mark as a new version')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

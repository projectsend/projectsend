import { SearchablePicker, type PickerOption } from '@/components/searchable-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Candidate {
    id: number;
    name: string;
    original_name: string;
}

interface NewVersionPickerProps {
    /** Endpoint returning `{files: Candidate[]}`, already narrowed to what this user may pick. */
    candidatesUrl: string;
    value: number | null;
    onChange: (id: number | null) => void;
    /**
     * Whether to warn that the upload will inherit the original's
     * recipients. True in the client portal, where the uploader may not
     * know who else holds the file they are revising.
     */
    warnAboutInheritedSharing?: boolean;
}

/**
 * "Is this a new version of a file you uploaded before?" — the upload-time
 * half of file versioning.
 *
 * Client portal only, deliberately. Staff already reach this from the file
 * editor's Versions tab, which the staff upload page redirects straight
 * into; and staff can upload many files at once, where a single "previous
 * version" for the whole batch has no sensible meaning. Clients have no
 * per-file editor, so upload time is the only moment they can say it.
 *
 * Collapsed by default: most uploads are not revisions, and an always-open
 * file picker above the drop zone would make the common case look like the
 * exception.
 *
 * The list comes from the server rather than the page's props because it
 * differs per actor — staff get their library, a client gets THEIR OWN
 * UPLOADS ONLY. That second rule is not cosmetic: a revision inherits the
 * recipients of the file it revises, so offering a client a file merely
 * shared with them would be offering a way to publish their upload to a
 * recipient list they do not own. The server enforces it again when the
 * upload completes; this only keeps the UI honest.
 */
export function NewVersionPicker({ candidatesUrl, value, onChange, warnAboutInheritedSharing = false }: NewVersionPickerProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [loading, setLoading] = useState(false);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const search = useCallback(
        (query: string) => {
            setLoading(true);

            fetch(`${candidatesUrl}?search=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : { files: [] }))
                .then((data: { files: Candidate[] }) => setCandidates(data.files))
                .catch(() => setCandidates([]))
                .finally(() => setLoading(false));
        },
        [candidatesUrl],
    );

    useEffect(() => {
        if (open) search('');
    }, [open, search]);

    const onSearchChange = (query: string) => {
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => search(query), 250);
    };

    const options: PickerOption[] = candidates.map((candidate) => ({
        id: candidate.id,
        label: candidate.name,
        hint: candidate.original_name,
    }));

    const selectedName = candidates.find((candidate) => candidate.id === value)?.name ?? null;

    return (
        <div className="mb-6 grid gap-3">
            <div className="flex items-start gap-2">
                <Checkbox
                    id="is-new-version"
                    checked={open}
                    onCheckedChange={(checked) => {
                        const next = checked === true;
                        setOpen(next);
                        if (!next) onChange(null);
                    }}
                />
                <Label htmlFor="is-new-version" className="font-normal">
                    {t('This is a new version of a file I uploaded before')}
                </Label>
            </div>

            {open && (
                <div className="grid max-w-md gap-2">
                    <SearchablePicker
                        options={options}
                        value={value}
                        onChange={onChange}
                        onSearchChange={onSearchChange}
                        loading={loading}
                        searchPlaceholder={t('Search your files…')}
                        emptyLabel={(query) =>
                            query === '' ? t('You have no earlier files that can be replaced.') : t('No files match ":search".', { search: query })
                        }
                    />

                    {/* Names the original — the uploader owns it — but never
                        its recipients: staff may have shared it with other
                        clients, and that list is not theirs to see. */}
                    {warnAboutInheritedSharing && selectedName !== null && (
                        <p className="text-muted-foreground text-sm">
                            {t('This file will be shared with the same people who have ":name".', { name: selectedName })}
                        </p>
                    )}

                    {value !== null && (
                        <Button type="button" variant="ghost" size="sm" className="w-fit" onClick={() => onChange(null)}>
                            {t('Clear')}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

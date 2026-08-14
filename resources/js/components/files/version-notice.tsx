import { type VersionLinks } from '@/components/files/version-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useTranslation } from '@/hooks/use-translation';
import { ArrowRight, TriangleAlert } from 'lucide-react';

interface VersionNoticeProps {
    version: VersionLinks | null | undefined;
    className?: string;
}

/**
 * The full statement of a file's version status, for a page about one file
 * — as opposed to `<VersionBadge>`, which is the one-word marker for a row.
 *
 * Used on the public single-file page, where it matters most: a visitor who
 * arrived from a link or a search result has no library view, no
 * notifications and no obvious way to ask, so an unmarked superseded
 * download is exactly the failure versioning exists to prevent.
 *
 * A guest only ever sees this when the counterpart is itself public and
 * unexpired — the props arrive already filtered (see
 * App\Modules\Files\Versions\FileVersionLinks), so a link here can never
 * point at a page that would 404.
 */
export function VersionNotice({ version, className }: VersionNoticeProps) {
    const { t } = useTranslation();

    if (!version) return null;

    if (version.next) {
        return (
            <Alert variant="warning" className={className}>
                <TriangleAlert className="size-4" />
                <AlertTitle>{t('There is a newer version of this file')}</AlertTitle>
                <AlertDescription>
                    {version.next.url ? (
                        <a href={version.next.url} className="inline-flex items-center gap-1 font-medium underline underline-offset-4">
                            {version.next.name}
                            <ArrowRight className="size-3.5" />
                        </a>
                    ) : (
                        version.next.name
                    )}
                </AlertDescription>
            </Alert>
        );
    }

    if (version.previous) {
        return (
            <p className={className}>
                <span className="text-muted-foreground text-sm">
                    {t('This replaces')}{' '}
                    {version.previous.url ? (
                        <a href={version.previous.url} className="underline underline-offset-4">
                            {version.previous.name}
                        </a>
                    ) : (
                        version.previous.name
                    )}
                </span>
            </p>
        );
    }

    return null;
}

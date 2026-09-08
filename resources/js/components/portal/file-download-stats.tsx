import { useTranslation } from '@/hooks/use-translation';
import { useFormatDate } from '@/hooks/use-format-date';
import { type FileRow } from '@/types/portal';

interface FileDownloadStatsProps {
    file: FileRow;
}

/**
 * "Did it arrive?" — how often a client's own file has gone out, and when
 * it last did.
 *
 * Renders nothing at all when `downloads` is null, which is every file
 * somebody shared *with* this client. That is a privacy rule, not a
 * tidiness one: a count on a file shared with several people tells each
 * of them about the others' activity. The server sends null rather than a
 * zero precisely so this cannot be shown by mistake — so gate on the
 * field being present and never on `is_mine`.
 *
 * Zero *is* rendered, as words. On a link-only account this is the only
 * evidence a customer has either way, and "nobody has taken it yet" is an
 * answer they came looking for.
 */
export function FileDownloadStats({ file }: FileDownloadStatsProps) {
    const { t } = useTranslation();
    const { date } = useFormatDate();

    if (file.downloads === null) {
        return null;
    }

    // Whole sentences rather than assembled fragments, and a singular
    // spelled out rather than a pipe: `t()` does no plural selection —
    // the catalogues are flat key/value and a "one|many" string would
    // reach the screen with its pipe intact. A count above zero always
    // has a date, since the date is the newest of the rows counted.
    if (file.downloads.count === 0) {
        return <span>{t('Not downloaded yet')}</span>;
    }

    const when = date(file.downloads.last_at);

    return (
        <span>
            {file.downloads.count === 1
                ? t('Downloaded once, on :date', { date: when })
                : t('Downloaded :count times, last on :date', { count: file.downloads.count, date: when })}
        </span>
    );
}

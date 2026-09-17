import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import { type FileRow } from '@/types/portal';

interface FileExpiryProps {
    file: FileRow;
}

/**
 * When a file stops being available, said on its row.
 *
 * Renders nothing for a file that never expires, which is most of them.
 * Its own component, like FileDownloadStats, so the four themes say it in
 * the same words and only decide where it sits.
 */
export function FileExpiry({ file }: FileExpiryProps) {
    const { t } = useTranslation();
    const { date } = useFormatDate();

    if (file.expires_at === null) {
        return null;
    }

    return <span>{t('Available until :date', { date: date(file.expires_at) })}</span>;
}

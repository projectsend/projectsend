import { Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { type DownloadLimit } from '@/types/portal';

/**
 * The download button, and the one place that knows what a spent
 * download limit looks like.
 *
 * There are eight themes across the portal and the public site, and the
 * props contract in docs/theming-files-checklist.md is explicit that a
 * theme renders a rule rather than re-deriving it. Left to each theme,
 * the disabled state would be missing from at least one of them within a
 * release — and a theme where the button still works is a theme where
 * the limit does not exist.
 *
 * The server refuses the download regardless (DownloadAllowance guards
 * every route that serves bytes). This is the half that stops a person
 * clicking something that was only ever going to fail.
 */
export function DownloadAction({
    href,
    limit,
    variant = 'outline',
    size = 'sm',
    iconOnly = false,
    className,
    iconClassName = 'size-4',
}: {
    href: string;
    limit: DownloadLimit;
    variant?: 'outline' | 'ghost' | 'default' | 'secondary';
    size?: 'sm' | 'lg' | 'default' | 'icon';
    /** Render the label for screen readers only, for icon-only rows. */
    iconOnly?: boolean;
    className?: string;
    /** Themes size and colour the icon differently; the rule does not change. */
    iconClassName?: string;
}) {
    const { t } = useTranslation();
    const label = t('Download');

    if (limit.blocked) {
        return (
            <Button variant={variant} size={size} className={className} disabled title={t('This file has reached its download limit.')}>
                <Download className={iconClassName} />
                {iconOnly ? <span className="sr-only">{label}</span> : label}
            </Button>
        );
    }

    return (
        <Button variant={variant} size={size} className={className} asChild>
            <a href={href}>
                <Download className={iconClassName} />
                {iconOnly ? <span className="sr-only">{label}</span> : label}
            </a>
        </Button>
    );
}

/**
 * How much of a capped file is left, for the row it sits on. Renders
 * nothing at all when the file is uncapped for this viewer — which
 * includes a file they uploaded themselves.
 */
export function DownloadLimitNote({ limit, className }: { limit: DownloadLimit; className?: string }) {
    const { t } = useTranslation();

    if (limit.limit === null) {
        return null;
    }

    return (
        <span className={className}>
            {limit.blocked ? t('Download limit reached') : t(':left of :limit downloads left', { left: limit.left ?? 0, limit: limit.limit })}
        </span>
    );
}

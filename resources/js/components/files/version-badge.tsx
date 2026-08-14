import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { History } from 'lucide-react';

export interface VersionLinks {
    previous: { id: number; name: string; url: string | null } | null;
    next: { id: number; name: string; url: string | null } | null;
}

interface VersionBadgeProps {
    version: VersionLinks | null | undefined;
    /**
     * Which theme's visual language to speak. The wording never changes —
     * only the chrome, the same split as the filter toolbar and the comment
     * shells (see docs/theming-files-checklist.md).
     */
    variant?: 'default' | 'compact' | 'drive' | 'gallery';
    className?: string;
}

/**
 * "Outdated — replaced by X" / "Replaces Y", or nothing.
 *
 * Nothing is the common case and the important one: the props this reads
 * are already filtered to what the viewer may be told (see
 * App\Modules\Files\Versions\FileVersionLinks). A theme must never decide
 * for itself whether to show a counterpart's name — by the time it gets
 * here, being present IS the permission.
 */
export function VersionBadge({ version, variant = 'default', className }: VersionBadgeProps) {
    const { t } = useTranslation();

    if (!version) return null;

    const outdated = version.next !== null;
    const label = outdated
        ? t('Replaced by :name', { name: version.next!.name })
        : version.previous !== null
          ? t('Replaces :name', { name: version.previous.name })
          : null;

    if (label === null) return null;

    const short = outdated ? t('Outdated') : t('New version');

    if (variant === 'compact') {
        // Monochrome, square, text-xs — this theme is a spreadsheet by
        // contract, so no badge chrome and no colour at all.
        return (
            <span className={cn('ml-1 text-[10px] tracking-wide text-neutral-500 uppercase dark:text-neutral-400', className)} title={label}>
                {short}
            </span>
        );
    }

    if (variant === 'drive') {
        return (
            <span className={cn('inline-flex items-center gap-1 text-xs text-neutral-500 dark:text-neutral-400', className)} title={label}>
                <History className="size-4" />
                {short}
            </span>
        );
    }

    if (variant === 'gallery') {
        // A pill over the thumbnail, in this theme's violet — a grid card
        // has no row to put a badge beside.
        return (
            <span className={cn('rounded-full bg-violet-600/90 px-2 py-0.5 text-[10px] font-medium text-white', className)} title={label}>
                {short}
            </span>
        );
    }

    return (
        <span
            className={cn(
                'bg-secondary text-secondary-foreground inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-normal',
                className,
            )}
            title={label}
        >
            <History className="size-3" />
            {short}
        </span>
    );
}

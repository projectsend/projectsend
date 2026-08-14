import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { type Crumb } from '@/types/portal';

interface PortalBreadcrumbProps {
    breadcrumb: Crumb[];
    folderUrl: (id: number | null) => string;
    /** Theme spacing and colour for the trail itself. */
    className?: string;
    /** Theme hover colour for each crumb. */
    linkClassName?: string;
}

/**
 * The "My files › Brand Kit › 2026" trail.
 *
 * Structurally the same in every theme; only spacing and hover colour differ,
 * so those come in as classes rather than as four copies of the markup.
 */
export function PortalBreadcrumb({ breadcrumb, folderUrl, className, linkClassName = 'hover:text-foreground' }: PortalBreadcrumbProps) {
    const { t } = useTranslation();

    return (
        <nav className={cn('text-muted-foreground flex flex-wrap items-center gap-1 text-sm', className)}>
            <Link href={folderUrl(null)} className={linkClassName}>
                {t('My files')}
            </Link>
            {breadcrumb.map((crumb) => (
                <span key={crumb.id} className="flex items-center gap-1">
                    <ChevronRight className="size-3.5" />
                    <Link href={folderUrl(crumb.id)} className={linkClassName}>
                        {crumb.name}
                    </Link>
                </span>
            ))}
        </nav>
    );
}

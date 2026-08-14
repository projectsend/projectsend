import { Badge } from '@/components/ui/badge';
import { categoryColor } from '@/lib/category-colors';
import { cn } from '@/lib/utils';

export interface CategoryTag {
    id: number;
    name: string;
    color: string;
}

/**
 * A file's categories, wherever that file is shown: the staff library, the
 * client portal, the guest-facing public pages and share links.
 *
 * Categories are not internal metadata — everyone who can reach a file can
 * see the labels on it, which is what the notice on /categories tells
 * admins. That is only true if every surface actually renders them, so this
 * lives in one place rather than being re-typed per theme, where a missed
 * copy would quietly make the notice a lie.
 *
 * Renders nothing when the list is empty, so callers never need their own
 * length check. `size` covers the one real difference between surfaces
 * (`compact` runs a step smaller, like everything else in that theme);
 * `className` is for the wrapper's spacing, which the caller owns.
 */
export function CategoryBadges({ categories, size = 'sm', className }: { categories: CategoryTag[]; size?: 'sm' | 'xs'; className?: string }) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <div className={cn('flex flex-wrap gap-1', className)}>
            {categories.map((category) => (
                <Badge
                    key={category.id}
                    variant="outline"
                    className={cn(size === 'xs' ? 'text-[10px]' : 'text-[11px]', 'font-normal', categoryColor(category.color).badge)}
                >
                    {category.name}
                </Badge>
            ))}
        </div>
    );
}

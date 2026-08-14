import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ALL } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import { categoryColor } from '@/lib/category-colors';
import { type CategoryTag, SORT_OPTIONS } from '@/types/portal';

interface PortalFilesFilterFieldsProps {
    categories: CategoryTag[];
    values: Record<string, string>;
    set: (key: string, value: string, debounce?: boolean) => void;
    setMany: (updates: Record<string, string>, debounce?: boolean) => void;
    /** Widen fields to fill their container instead of using fixed widths — for stacked/vertical layouts. */
    fullWidth?: boolean;
}

/**
 * The Search, Category, Owner and Sort fields for the "My files" page, with
 * no wrapping shell.
 *
 * The fields themselves are identical in every theme — the controls are the
 * same controls, and a theme that quietly offered a different set of filters
 * would be a bug rather than a style. Themes vary in how this set is
 * presented and reached (see the per-theme toolbar components in this
 * directory), not in what can be asked of them.
 */
export function PortalFilesFilterFields({ categories, values, set, setMany, fullWidth }: PortalFilesFilterFieldsProps) {
    const { t } = useTranslation();
    const fieldWidth = fullWidth ? 'w-full' : '';

    return (
        <>
            <FilterField label={t('Search')} htmlFor="my-files-search">
                <Input
                    id="my-files-search"
                    type="search"
                    placeholder={t('Search your files')}
                    className={fullWidth ? fieldWidth : 'w-56'}
                    value={values.search}
                    onChange={(e) => set('search', e.target.value, true)}
                />
            </FilterField>
            <FilterField label={t('Category')} htmlFor="my-files-category">
                <Select value={values.category} onValueChange={(v) => set('category', v)}>
                    <SelectTrigger id="my-files-category" className={fullWidth ? fieldWidth : 'w-40'}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t('All categories')}</SelectItem>
                        {categories.map((c) => (
                            <SelectItem key={c.id} value={String(c.id)}>
                                <span className="flex items-center gap-2">
                                    <span className={`size-2 shrink-0 rounded-full ${categoryColor(c.color).swatch}`} />
                                    {c.name}
                                </span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>
            <FilterField label={t('Owner')} htmlFor="my-files-owner">
                <Select value={values.owner} onValueChange={(v) => set('owner', v)}>
                    <SelectTrigger id="my-files-owner" className={fullWidth ? fieldWidth : 'w-40'}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t('Everyone')}</SelectItem>
                        <SelectItem value="mine">{t('Uploaded by me')}</SelectItem>
                        <SelectItem value="shared">{t('Shared with me')}</SelectItem>
                    </SelectContent>
                </Select>
            </FilterField>
            <FilterField label={t('Sort by')} htmlFor="my-files-sort">
                <Select
                    value={`${values.sort}-${values.direction}`}
                    onValueChange={(v) => {
                        const [field, dir] = v.split('-');
                        setMany({ sort: field, direction: dir });
                    }}
                >
                    <SelectTrigger id="my-files-sort" className={fullWidth ? fieldWidth : 'w-40'}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {SORT_OPTIONS.map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {t(label)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>
        </>
    );
}

interface PortalFilesToolbarProps {
    categories: CategoryTag[];
    values: Record<string, string>;
    set: (key: string, value: string, debounce?: boolean) => void;
    setMany: (updates: Record<string, string>, debounce?: boolean) => void;
    reset: () => void;
    hasFilters: boolean;
}

/** The default theme's toolbar shell: fields inline in a bordered box. */
export function PortalFilesToolbar({ categories, values, set, setMany, reset, hasFilters }: PortalFilesToolbarProps) {
    return (
        <ListToolbar showClear={hasFilters} onClear={reset}>
            <PortalFilesFilterFields categories={categories} values={values} set={set} setMany={setMany} />
        </ListToolbar>
    );
}

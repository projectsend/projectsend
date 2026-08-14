import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { ListToolbar } from '@/components/list-toolbar';
import { TableShell } from '@/components/table-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { categoryColor } from '@/lib/category-colors';

interface CategoryRow {
    id: number;
    name: string;
    color: string;
    files_count: number;
}

interface CategoriesIndexProps {
    categories: CategoryRow[];
    filters: { search: string | null };
    can_create: boolean;
    can_edit: boolean;
    can_delete: boolean;
}

export default function CategoriesIndex({ categories, filters, can_create, can_edit, can_delete }: CategoriesIndexProps) {
    const { t } = useTranslation();

    const { values, set, reset, hasFilters } = useListQuery('categories.index', { search: filters.search ?? '' });

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Categories'), href: '/categories' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Categories')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Categories')} description={t('Flat labels for files — independent of folders. A file can have several.')} />
                    {can_create && (
                        <Button asChild>
                            <Link href={route('categories.create')}>{t('New category')}</Link>
                        </Button>
                    )}
                </div>

                <Alert className="mb-6">
                    <Eye className="size-4" />
                    <AlertTitle>{t('Categories are visible outside your team')}</AlertTitle>
                    <AlertDescription>
                        {t(
                            'Anyone who can reach a file sees the categories assigned to it — a client in their portal, or anyone holding a public or shared link. Signed-in clients can also filter their files by any category in this list. Only your team can create, rename or delete them.',
                        )}
                    </AlertDescription>
                </Alert>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <Input
                        type="search"
                        placeholder={t('Search categories')}
                        className="w-64"
                        value={values.search}
                        onChange={(e) => set('search', e.target.value, true)}
                    />
                </ListToolbar>

                <TableShell
                    columns={[t('Name'), t('Files'), null]}
                    isEmpty={categories.length === 0}
                    emptyMessage={<>{hasFilters ? t('No categories match your search.') : t('No categories yet.')}</>}
                >
                    {categories.map((category) => (
                        <tr key={category.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5 font-medium">
                                <span className="flex items-center gap-2">
                                    <span className={`size-2.5 shrink-0 rounded-full ${categoryColor(category.color).swatch}`} />
                                    {category.name}
                                </span>
                            </td>
                            <td className="text-muted-foreground px-4 py-2.5">{category.files_count}</td>
                            <td className="px-4 py-2.5 text-right">
                                <div className="flex justify-end gap-1">
                                    {can_edit && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={route('categories.edit', category.id)}>{t('Edit')}</Link>
                                        </Button>
                                    )}
                                    {can_delete && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                    {t('Delete')}
                                                </Button>
                                            }
                                            title={t('Delete category?')}
                                            description={t(
                                                'The category ":name" will be removed from all its files. The files themselves are not deleted.',
                                                { name: category.name },
                                            )}
                                            confirmLabel={t('Delete category')}
                                            onConfirm={() => router.delete(route('categories.destroy', category.id), { preserveScroll: true })}
                                        />
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                </TableShell>
            </div>
        </AppLayout>
    );
}

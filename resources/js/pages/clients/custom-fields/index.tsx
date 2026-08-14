import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { ListToolbar } from '@/components/list-toolbar';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface CustomFieldRow {
    id: number;
    label: string;
    type: string;
    options: string[] | null;
    required: boolean;
}

interface CustomFieldsIndexProps {
    fields: CustomFieldRow[];
    filters: { search: string | null };
}

const typeLabels: Record<string, string> = {
    text: 'Text',
    textarea: 'Multi-line text',
    select: 'Dropdown',
    checkbox: 'Checkbox',
};

export default function CustomFieldsIndex({ fields, filters }: CustomFieldsIndexProps) {
    const { t } = useTranslation();

    const { values, set, reset, hasFilters } = useListQuery('client-custom-fields.index', { search: filters.search ?? '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: '/clients' },
        { title: t('Custom fields'), href: '/client-custom-fields' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Custom fields')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Custom fields')} description={t('Extra fields shown on the client create and edit screens.')} />
                    <Button asChild>
                        <Link href={route('client-custom-fields.create')}>{t('New field')}</Link>
                    </Button>
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <Input
                        type="search"
                        placeholder={t('Search fields')}
                        className="w-64"
                        value={values.search}
                        onChange={(e) => set('search', e.target.value, true)}
                    />
                </ListToolbar>

                <TableShell
                    columns={[t('Label'), t('Type'), t('Required'), null]}
                    isEmpty={fields.length === 0}
                    emptyMessage={<>{hasFilters ? t('No fields match your search.') : t('No custom fields yet.')}</>}
                >
                    {fields.map((field) => (
                        <tr key={field.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5 font-medium">{field.label}</td>
                            <td className="text-muted-foreground px-4 py-2.5">{t(typeLabels[field.type] ?? field.type)}</td>
                            <td className="px-4 py-2.5">{field.required && <Badge variant="outline">{t('Required')}</Badge>}</td>
                            <td className="px-4 py-2.5 text-right">
                                <div className="flex justify-end gap-1">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={route('client-custom-fields.edit', field.id)}>{t('Edit')}</Link>
                                    </Button>
                                    <ConfirmDialog
                                        trigger={
                                            <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                {t('Delete')}
                                            </Button>
                                        }
                                        title={t('Delete field?')}
                                        description={t('The field ":name" and all its stored values will be permanently deleted.', {
                                            name: field.label,
                                        })}
                                        confirmLabel={t('Delete field')}
                                        onConfirm={() => router.delete(route('client-custom-fields.destroy', field.id), { preserveScroll: true })}
                                    />
                                </div>
                            </td>
                        </tr>
                    ))}
                </TableShell>
            </div>
        </AppLayout>
    );
}

import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { CategoryForm } from '@/components/category-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { SavedIndicator } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface CategoriesEditProps {
    category: {
        id: number;
        name: string;
        color: string;
    };
    colors: string[];
}

interface CategoryFormData {
    [key: string]: string;
    name: string;
    color: string;
}

export default function CategoriesEdit({ category, colors }: CategoriesEditProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Categories'), href: '/categories' },
        { title: category.name, href: `/categories/${category.id}` },
    ];

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm<CategoryFormData>({
        name: category.name,
        color: category.color,
    });

    const deleteForm = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('categories.update', category.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={category.name} />

            <div className="px-4 py-6">
                <Heading title={category.name} />

                <form onSubmit={submit} className="space-y-6">
                    <CategoryForm
                        name={data.name}
                        color={data.color}
                        colors={colors}
                        onChange={(field, value) => setData(field, value)}
                        errors={errors}
                    />

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>

                        {auth.permissions.includes('delete_categories') && (
                            <ConfirmDialog
                                trigger={
                                    <Button type="button" variant="destructive" disabled={deleteForm.processing}>
                                        {t('Delete category')}
                                    </Button>
                                }
                                title={t('Delete category?')}
                                description={t('The category ":name" will be removed from all its files. The files themselves are not deleted.', {
                                    name: category.name,
                                })}
                                confirmLabel={t('Delete category')}
                                onConfirm={() => deleteForm.delete(route('categories.destroy', category.id))}
                            />
                        )}

                        <SavedIndicator recentlySuccessful={recentlySuccessful} />
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

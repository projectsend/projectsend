import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { CategoryForm } from '@/components/category-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface CategoryFormData {
    [key: string]: string;
    name: string;
    color: string;
}

interface CategoriesCreateProps {
    colors: string[];
}

export default function CategoriesCreate({ colors }: CategoriesCreateProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Categories'), href: '/categories' },
        { title: t('New category'), href: '/categories/create' },
    ];

    const { data, setData, post, processing, errors } = useForm<CategoryFormData>({ name: '', color: colors[0] });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('categories.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New category')} />

            <div className="px-4 py-6">
                <Heading title={t('New category')} description={t('Flat labels for files — independent of folders. A file can have several.')} />

                <form onSubmit={submit} className="space-y-6">
                    <CategoryForm
                        name={data.name}
                        color={data.color}
                        colors={colors}
                        onChange={(field, value) => setData(field, value)}
                        errors={errors}
                    />

                    <Button type="submit" disabled={processing}>
                        {t('Create category')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}

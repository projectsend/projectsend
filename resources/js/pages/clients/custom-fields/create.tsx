import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ClientCustomFieldForm, type ClientCustomFieldFormData } from '@/components/client-custom-field-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface CustomFieldsCreateProps {
    types: string[];
}

export default function CustomFieldsCreate({ types }: CustomFieldsCreateProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: '/clients' },
        { title: t('Custom fields'), href: '/client-custom-fields' },
        { title: t('New field'), href: '/client-custom-fields/create' },
    ];

    const { data, setData, post, processing, errors, transform } = useForm<ClientCustomFieldFormData>({
        label: '',
        type: types[0] ?? 'text',
        options: [],
        required: false,
        client_editability: 'hidden',
        client_contexts: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((payload) => ({ ...payload, options: payload.options.filter((option) => option !== '') }));
        post(route('client-custom-fields.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New field')} />

            <div className="px-4 py-6">
                <Heading title={t('New field')} description={t('Extra fields shown on the client create and edit screens.')} />

                <form onSubmit={submit} className="space-y-6">
                    <ClientCustomFieldForm data={data} types={types} onChange={setData} errors={errors} />

                    <Button type="submit" disabled={processing}>
                        {t('Create field')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}

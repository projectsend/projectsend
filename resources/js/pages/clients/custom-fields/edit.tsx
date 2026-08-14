import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ClientCustomFieldForm, type ClientCustomFieldFormData } from '@/components/client-custom-field-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { SavedIndicator } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface CustomFieldsEditProps {
    field: {
        id: number;
        label: string;
        type: string;
        options: string[] | null;
        required: boolean;
        client_editability: string;
        client_contexts: string[];
    };
    types: string[];
}

export default function CustomFieldsEdit({ field, types }: CustomFieldsEditProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: '/clients' },
        { title: t('Custom fields'), href: '/client-custom-fields' },
        { title: field.label, href: `/client-custom-fields/${field.id}` },
    ];

    const { data, setData, patch, processing, errors, recentlySuccessful, transform } = useForm<ClientCustomFieldFormData>({
        label: field.label,
        type: field.type,
        options: field.options ?? [],
        required: field.required,
        client_editability: field.client_editability,
        client_contexts: field.client_contexts,
    });

    const deleteForm = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((payload) => ({ ...payload, options: payload.options.filter((option) => option !== '') }));
        patch(route('client-custom-fields.update', field.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={field.label} />

            <div className="px-4 py-6">
                <Heading title={field.label} />

                <form onSubmit={submit} className="space-y-6">
                    <ClientCustomFieldForm data={data} types={types} onChange={setData} errors={errors} />

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>

                        <ConfirmDialog
                            trigger={
                                <Button type="button" variant="destructive" disabled={deleteForm.processing}>
                                    {t('Delete field')}
                                </Button>
                            }
                            title={t('Delete field?')}
                            description={t('The field ":name" and all its stored values will be permanently deleted.', { name: field.label })}
                            confirmLabel={t('Delete field')}
                            onConfirm={() => deleteForm.delete(route('client-custom-fields.destroy', field.id))}
                        />

                        <SavedIndicator recentlySuccessful={recentlySuccessful} />
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

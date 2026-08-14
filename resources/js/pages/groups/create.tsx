import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { GroupForm } from '@/components/group-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface GroupFormData {
    [key: string]: string | boolean;
    name: string;
    slug: string;
    description: string;
    public: boolean;
}

export default function GroupsCreate() {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Groups'), href: '/groups' },
        { title: t('New group'), href: '/groups/create' },
    ];

    const { data, setData, post, processing, errors } = useForm<GroupFormData>({
        name: '',
        slug: '',
        description: '',
        public: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('groups.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New group')} />

            <div className="px-4 py-6">
                <Heading title={t('New group')} description={t('Groups let you assign files to several clients at once')} />

                <form onSubmit={submit} className="space-y-6">
                    <GroupForm
                        name={data.name}
                        slug={data.slug}
                        description={data.description}
                        isPublic={data.public}
                        onChange={(field, value) => setData(field, value)}
                        errors={errors}
                    />

                    <Button type="submit" disabled={processing}>
                        {t('Create group')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}

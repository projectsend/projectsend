import { Head } from '@inertiajs/react';

import DeleteUser from '@/components/delete-user';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';

/**
 * Deleting your account gets its own screen rather than sitting at the
 * bottom of the profile form. It is the one irreversible thing on this
 * side of the application, and a destructive block under a Save button is
 * a place to land on by accident — reaching it should be a deliberate
 * navigation, not a scroll.
 */
export default function DeleteAccount({ erasureGraceDays }: { erasureGraceDays: number }) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/settings/profile' },
        { title: t('Delete account'), href: '/settings/delete-account' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Delete account')} />

            <SettingsLayout>
                <DeleteUser graceDays={erasureGraceDays} />
            </SettingsLayout>
        </AppLayout>
    );
}

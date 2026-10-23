import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

interface AuthorizeErrorProps {
    message: string;
}

/**
 * What the person sees when an authorization cannot proceed and there
 * is no validated address to send them back to (design §4.1).
 */
export default function AuthorizeError({ message }: AuthorizeErrorProps) {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('This assistant cannot connect')} description={message}>
            <Head title={t('This assistant cannot connect')} />

            <div className="flex justify-center">
                <Button asChild variant="outline">
                    <Link href={route('home')}>{t('Back to the site')}</Link>
                </Button>
            </div>
        </AuthLayout>
    );
}

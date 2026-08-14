import { Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

export default function SetupSuccess() {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('ProjectSend is ready')} description={t('The administrator account was created successfully.')}>
            <Head title={t('Setup complete')} />

            <div className="flex flex-col items-center gap-6">
                <CircleCheck className="text-primary size-12" strokeWidth={1.5} />

                <Button asChild className="w-full">
                    <TextLink href={route('login')} className="text-primary-foreground no-underline">
                        {t('Go to the login form')}
                    </TextLink>
                </Button>
            </div>
        </AuthLayout>
    );
}

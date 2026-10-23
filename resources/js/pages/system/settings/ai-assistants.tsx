import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface AiAssistantSettingsProps {
    ai_connector_enabled: boolean;
    endpoint: string;
}

export default function AiAssistantSettings({ ai_connector_enabled, endpoint }: AiAssistantSettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('AI assistants'), href: '/system/settings/ai-assistants' },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        ai_connector_enabled,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.ai-assistants.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('AI assistant settings')} />

            <div className="px-4 py-6">
                <Heading title={t('AI assistant settings')} description={t('Whether people can connect an AI assistant to this site')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="ai_connector_enabled"
                                checked={data.ai_connector_enabled}
                                onCheckedChange={(checked) => setData('ai_connector_enabled', checked === true)}
                            />
                            <Label htmlFor="ai_connector_enabled" className="font-normal">
                                {t('Allow AI assistants to connect')}
                            </Label>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Staff can connect Claude, ChatGPT or another assistant to their account. The assistant can only do what that person can do on the web, and everything it does appears in the activity log as "AI assistant".',
                            )}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {t('Turning this off stops every connected assistant at once. Turning it back on lets them in again.')}
                        </p>
                        <InputError className="mt-2" message={errors.ai_connector_enabled} />
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Address to connect to')}</Label>
                        <code className="bg-muted w-fit rounded px-2 py-1 text-sm break-all">{endpoint}</code>
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}

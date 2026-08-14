import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Check, Copy, ExternalLink } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface PublicListingSettingsProps {
    public_listing_enabled: boolean;
    public_listing_slug: string;
}

export default function PublicListingSettings({ public_listing_enabled, public_listing_slug }: PublicListingSettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Public listing'), href: '/system/settings/public-listing' },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        public_listing_enabled: public_listing_enabled,
        public_listing_slug: public_listing_slug,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.public-listing.update'));
    };

    const publicListingUrl = `${window.location.origin}/${data.public_listing_slug}`;

    const [copied, setCopied] = useState(false);
    const copyPublicListingUrl = () => {
        void navigator.clipboard.writeText(publicListingUrl);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1500);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Public listing settings')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Public listing settings')}
                    description={t(
                        "A public group's page is always reachable by its own link once you mark it public — this page only controls the base URL and an optional directory listing every public group and file at once.",
                    )}
                />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="public_listing_enabled"
                                checked={data.public_listing_enabled}
                                onCheckedChange={(checked) => setData('public_listing_enabled', checked === true)}
                            />
                            <Label htmlFor="public_listing_enabled" className="font-normal">
                                {t('Enable the public directory page')}
                            </Label>
                        </div>
                        <InputError message={errors.public_listing_enabled} />
                    </div>

                    {data.public_listing_enabled && (
                        <div className="grid gap-2">
                            <Label htmlFor="public_listing_slug">{t('Public listing URL')}</Label>
                            <Input
                                id="public_listing_slug"
                                className="mt-1 block w-full"
                                value={data.public_listing_slug}
                                onChange={(e) => setData('public_listing_slug', e.target.value)}
                                required
                            />
                            <p className="text-muted-foreground text-sm">
                                {t('The base URL segment for public group pages, e.g. :url', {
                                    url: data.public_listing_slug ? publicListingUrl : `${window.location.origin}/…`,
                                })}
                            </p>
                            <InputError message={errors.public_listing_slug} />

                            {data.public_listing_slug && (
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Button type="button" variant="outline" size="sm" asChild>
                                            <a href={publicListingUrl} target="_blank" rel="noreferrer">
                                                <ExternalLink className="size-4" />
                                                {t('Open public listing')}
                                            </a>
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" onClick={copyPublicListingUrl}>
                                            {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                                            {t('Copy URL')}
                                        </Button>
                                    </div>
                                    {(!public_listing_enabled || data.public_listing_slug !== public_listing_slug) && (
                                        <p className="text-muted-foreground text-xs">
                                            {t(
                                                "Save your changes to make this live — until then, these buttons point to a page that isn't published yet.",
                                            )}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}

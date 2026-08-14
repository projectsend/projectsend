import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';

import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';
import { formatBytes } from '@/lib/format-bytes';

interface ShareShowProps {
    status: 'active' | 'expired' | 'limit_reached' | 'not_found';
    file?: {
        original_name: string;
        size: number;
        categories: CategoryTag[];
    };
    download_url?: string;
}

export default function ShareShow({ status, file, download_url }: ShareShowProps) {
    const { t } = useTranslation();

    if (status !== 'active' || !file || !download_url) {
        const description =
            status === 'expired'
                ? t('This link has expired.')
                : status === 'limit_reached'
                  ? t('This link has reached its download limit.')
                  : t("This link doesn't exist or has been revoked.");

        return (
            <AuthLayout title={t('Link unavailable')} description={description}>
                <Head title={t('Link unavailable')} />
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={file.original_name} description={formatBytes(file.size)}>
            <Head title={file.original_name} />

            <CategoryBadges categories={file.categories} className="mb-4 justify-center" />

            <Button asChild className="w-full">
                <a href={download_url}>
                    <Download className="size-4" /> {t('Download')}
                </a>
            </Button>
        </AuthLayout>
    );
}

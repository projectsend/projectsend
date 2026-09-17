import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';

import { CategoryBadges, type CategoryTag } from '@/components/files/category-badges';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';
import { formatBytes } from '@/lib/format-bytes';

interface ShareShowProps {
    status: 'active' | 'expired' | 'limit_reached' | 'not_found' | 'checking' | 'unavailable';
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
                  : // A link can exist before its file has been checked for
                    // viruses — on some installations one is created the
                    // moment a file is uploaded — so this is "come back in a
                    // minute", not "something is wrong".
                    status === 'checking'
                    ? t('This file is still being checked for viruses. Please try again in a few minutes.')
                    : status === 'unavailable'
                      ? t('This file is not available.')
                      : t("This link doesn't exist or has been revoked.");

        const title = status === 'checking' ? t('Almost ready') : t('Link unavailable');

        return (
            <AuthLayout title={title} description={description}>
                <Head title={title} />
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

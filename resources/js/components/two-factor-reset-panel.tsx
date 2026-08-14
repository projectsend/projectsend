import { useForm } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

interface TwoFactorResetPanelProps {
    /** Whether the account currently has a confirmed second factor. */
    enabled: boolean;
    /** The account holder's name, for the copy. */
    name: string;
    /** The DELETE endpoint that removes it. */
    resetUrl: string;
}

/**
 * The lockout remedy, shown on the staff and client edit screens alike:
 * an account whose authenticator app is gone cannot be opened by anybody,
 * including the administrator looking at this page.
 *
 * Rendered even when there is nothing to remove, deliberately — "this
 * account does not use an authenticator app" is the answer to the support
 * question that brought the administrator here, and a panel that appears
 * only sometimes is one they have to go looking for.
 */
export function TwoFactorResetPanel({ enabled, name, resetUrl }: TwoFactorResetPanelProps) {
    const { t } = useTranslation();
    const form = useForm({});

    return (
        <div className="rounded-md border p-4">
            <h3 className="text-sm font-medium">{t('Two-factor authentication')}</h3>
            <p className="text-muted-foreground mt-1 text-sm">
                {enabled
                    ? t('This account signs in with an authenticator app. Remove it only if the account holder has lost both the app and their recovery codes.')
                    : t('This account does not use an authenticator app.')}
            </p>

            {enabled && (
                <ConfirmDialog
                    trigger={
                        <Button type="button" variant="outline" className="mt-3" disabled={form.processing}>
                            {t('Remove two-factor authentication')}
                        </Button>
                    }
                    title={t('Remove two-factor authentication?')}
                    description={t(
                        ':name will sign in with just their password until they set it up again, and will be emailed about this change. If two-factor authentication is required on this site, they will be asked to enrol again at their next sign-in.',
                        { name },
                    )}
                    confirmLabel={t('Remove two-factor authentication')}
                    onConfirm={() => form.delete(resetUrl, { preserveScroll: true })}
                />
            )}
        </div>
    );
}

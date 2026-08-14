import { usePage } from '@inertiajs/react';

import { ProviderIcon } from '@/components/provider-icon';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { type SharedData } from '@/types';

/**
 * The provider buttons under the password form.
 *
 * Plain links rather than a form post: this begins an OAuth redirect, and
 * the CSRF defence for the round trip is the `state` parameter Socialite
 * writes into the session, not a token on the way out.
 *
 * Renders nothing at all when no provider is configured, which is the
 * default — an installation that does not use this should not carry a
 * divider and an empty row.
 */
export function SocialLoginButtons({ label }: { label?: string }) {
    const { t } = useTranslation();
    const { social_login: providers } = usePage<SharedData>().props;

    if (providers.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-4">
            <div className="relative">
                <div className="absolute inset-0 flex items-center">
                    <span className="border-border w-full border-t" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-background text-muted-foreground px-2">{label ?? t('Or continue with')}</span>
                </div>
            </div>

            <div className="grid gap-2">
                {providers.map((provider) => (
                    <Button key={provider.provider} variant="outline" asChild className="w-full">
                        <a href={route('social.redirect', { provider: provider.provider })}>
                            <ProviderIcon provider={provider.provider} className="h-4 w-4" />
                            {t('Continue with :provider', { provider: provider.label })}
                        </a>
                    </Button>
                ))}
            </div>
        </div>
    );
}

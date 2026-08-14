import { KeyRound } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * A recognisable mark for each identity provider.
 *
 * Deliberately a coloured lettermark rather than each vendor's official
 * logo: the logos are trademarks with their own usage rules and asset
 * files, and shipping approximations of them would look worse than not
 * shipping them. The brand colour carries most of the recognition, and
 * the button says the provider's name next to it.
 */
const BRAND: Record<string, { color: string; initial: string }> = {
    google: { color: '#4285F4', initial: 'G' },
    microsoft: { color: '#00A4EF', initial: 'M' },
    facebook: { color: '#1877F2', initial: 'f' },
    linkedin: { color: '#0A66C2', initial: 'in' },
    github: { color: '#181717', initial: 'GH' },
};

export function ProviderIcon({ provider, className }: { provider: string; className?: string }) {
    const brand = BRAND[provider];

    if (!brand) {
        // Generic OpenID Connect: no brand at all, because it stands for
        // whichever server this installation pointed it at.
        return <KeyRound className={className} aria-hidden />;
    }

    return (
        <span
            aria-hidden
            className={cn('inline-flex shrink-0 items-center justify-center rounded-sm font-semibold text-white', className)}
            style={{ backgroundColor: brand.color, fontSize: brand.initial.length > 1 ? '0.5rem' : '0.625rem' }}
        >
            {brand.initial}
        </span>
    );
}

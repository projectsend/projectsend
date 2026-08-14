import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

import { useTranslation } from '@/hooks/use-translation';

/**
 * The one line that names ProjectSend to clients and anonymous
 * visitors, on the public pages, the sign-in pages and the client
 * portal.
 *
 * Deliberately no version number. Every one of these surfaces is
 * reachable without signing in, and printing the exact release there
 * tells anyone scanning which advisories apply to this installation.
 * The version belongs on the staff side — the sidebar line and
 * /system/about — where the people who would act on it are.
 *
 * Renders nothing at all when the installation has white-labelled
 * itself; `attribution` is true everywhere unless the cloud-only
 * Branding module says otherwise.
 *
 * Takes its own classes rather than carrying any: the four themes keep
 * visibly different footers on purpose (see
 * docs/theming-files-checklist.md), and this is one of the few pieces
 * every one of them shares.
 */
export function PoweredBy({ className }: { className?: string }) {
    const { t } = useTranslation();
    const { attribution, links } = usePage<SharedData>().props;

    if (!attribution) {
        return null;
    }

    return (
        <a href={links.website} target="_blank" rel="noreferrer" className={className}>
            {t('Powered by ProjectSend')}
        </a>
    );
}

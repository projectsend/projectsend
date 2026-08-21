import { UpdateOptionsDialog } from '@/components/update-options-dialog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type InstallKind = 'container' | 'container-source' | 'manual';

/**
 * Whether this installation runs in a container, whichever way it got
 * there. Everything that is about the runtime — restarting it, recreating
 * it — is the same answer for both container kinds; only the upgrade
 * itself differs.
 */
export function isContainerInstall(kind: InstallKind): boolean {
    return kind === 'container' || kind === 'container-source';
}

/**
 * How to actually apply an available update, for this server.
 *
 * One component rather than a string in each place that needs it, because
 * there were two places and they were both wrong in the same way: each
 * hardcoded `docker compose pull && docker compose up -d`, written back when
 * Docker was the only supported way to install. Somebody who unpacked a
 * release zip onto their own server was told to run a command they do not
 * have, for a stack they are not using, at the exact moment they were trying
 * to do the right thing.
 *
 * The same string was wrong a second way, for containers this time: a stack
 * built from a checkout has no image to pull, so both commands succeed,
 * report success, and change nothing at all. That install then keeps being
 * offered the same release for as long as its operator keeps following the
 * instructions on this screen (#1661) — which is why 'container' and
 * 'container-source' are told different things.
 *
 * `compact` is for the dashboard card, a narrow column beside other widgets.
 * `codeClassName` exists for the same caller — its code sits inside a warning
 * alert and has to match it.
 *
 * This used to print the whole nine-command sequence, which was a third copy
 * of something that also lived in INSTALL.md and UPDATE.md and had already
 * drifted from both. It is now one script, and the reason it can be is that
 * projectsend:update owns every part of an update that does not need root —
 * see UpdateInstallation.
 */
export function UpdateInstructions({
    kind,
    compact = false,
    codeClassName,
}: {
    kind: InstallKind;
    compact?: boolean;
    codeClassName?: string;
}) {
    const { t } = useTranslation();

    if (kind === 'container') {
        return (
            <p>
                {t('To update, run:')} <code className={cn('rounded px-2 py-1.5', codeClassName ?? 'bg-muted')}>docker compose pull && docker compose up -d</code>
            </p>
        );
    }

    if (kind === 'container-source') {
        return (
            <>
                {/* On its own line rather than inline like the image's one-liner:
                    this command is long enough to wrap inside the dashboard's
                    narrow column, and a command split across two lines mid-word
                    is one somebody retypes wrongly. */}
                <p className="text-muted-foreground mb-1">{t('To update, run this in the directory you cloned:')}</p>
                <code className={cn('block rounded px-2 py-1.5 break-words', codeClassName ?? 'bg-muted')}>git pull && docker compose up -d --build</code>
                <p className="text-muted-foreground mt-1 text-xs">
                    {t(
                        'This installation builds its own images, so pulling one changes nothing. If the release moved composer.lock or the frontend, run composer install and npm run build as well — UPDATE.md has the full procedure.',
                    )}
                </p>
            </>
        );
    }

    if (compact) {
        return (
            <div className="text-muted-foreground space-y-1">
                <p>{t('To update, run sudo ./update.sh in the install directory — it asks before it downloads or changes anything.')}</p>
                <UpdateOptionsDialog />
            </div>
        );
    }

    return (
        <>
            <p className="text-muted-foreground mb-1">{t('To update, run this in the install directory:')}</p>
            <code className={cn('block rounded px-2 py-1.5 whitespace-pre-wrap', codeClassName ?? 'bg-muted')}>
                {[
                    'cd /var/www/projectsend',
                    'sudo ./update.sh',
                ].join('\n')}
            </code>
            <p className="text-muted-foreground mt-1 text-xs">
                {t(
                    'It asks before checking for a release, before downloading one, and before touching your installation — and verifies the checksum of what it downloads. UPDATE.md has the full procedure.',
                )}
            </p>
            <div className="mt-1">
                <UpdateOptionsDialog />
            </div>
        </>
    );
}

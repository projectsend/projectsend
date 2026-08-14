import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type InstallKind = 'container' | 'manual';

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
 * `compact` is for the dashboard card, a narrow column beside other widgets:
 * the manual sequence is seven lines and would swamp it, so there it points
 * at the dialog, which has the room. `codeClassName` exists for the same
 * caller — its code sits inside a warning alert and has to match it.
 *
 * The full manual sequence is deliberately the whole thing, backup included.
 * It is the order INSTALL.md gives, and the step people skip is the one that
 * matters: migrations move forwards, not backwards.
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

    if (compact) {
        return (
            <p className="text-muted-foreground">
                {t('To update, follow the steps in INSTALL.md — back up first, then unpack the release and run the migrations.')}
            </p>
        );
    }

    return (
        <>
            <p className="text-muted-foreground mb-1">{t('To update, back up your database and files, then:')}</p>
            <code className={cn('block rounded px-2 py-1.5 whitespace-pre-wrap', codeClassName ?? 'bg-muted')}>
                {[
                    'php artisan down',
                    '# unpack the new release over this directory, keeping .env and storage/',
                    'php artisan migrate --force',
                    'php artisan projectsend:ensure-roles',
                    'php artisan optimize:clear',
                    'php artisan queue:restart',
                    'php artisan up',
                ].join('\n')}
            </code>
            <p className="text-muted-foreground mt-1 text-xs">{t('Run these as the user your web server runs as. INSTALL.md has the full procedure.')}</p>
        </>
    );
}

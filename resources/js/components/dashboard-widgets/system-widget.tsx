import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowUpCircle, HardDrive, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

import { FileDeliveryDialog, type FileDelivery } from '@/components/file-delivery-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { UpdateInstructions, type InstallKind } from '@/components/update-instructions';
import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/format-bytes';

export interface StorageDurability {
    level: 'durable' | 'docker_volume' | 'ephemeral' | 'unknown';
    volume: string | null;
    source: string | null;
}

export interface SystemInfo {
    version: string;
    edition: string;
    php: string;
    laravel: string;
    database: string;
    storage_used_bytes: number;
    storage_free_bytes: number;
    storage_driver: string;
    upload_temp_free_bytes: number;
    update_available: boolean;
    latest_version: string | null;
    release_url: string | null;
    // Null when the question does not apply: not running in a container, or
    // uploads go to external object storage rather than this server's disk.
    storage_durability: StorageDurability | null;
    /** Decides which upgrade instructions this card prints. */
    install_kind: InstallKind;
    /** How downloads leave the server — see FileDeliveryDialog. */
    file_delivery: FileDelivery;
    /**
     * Only ever present when something is wrong with virus scanning, and
     * null when scanning is off. A scanner that has stopped answering
     * looks, from every other screen, exactly like one that is working:
     * uploads keep arriving and downloads keep working, because that is
     * the configured behaviour. This is where that gets said out loud.
     */
    scanning: {
        /** False means no scanner is configured at all. */
        configured: boolean;
        reachable: boolean;
        engine: string | null;
        definitions_age_hours: number | null;
        let_through_24h: number;
        pending: number;
    } | null;
    /** Files in the library whose bytes are gone. Zero is the ordinary answer. */
    missing_files: number;
}

/**
 * What the delivery row says, per method.
 *
 * Named after the mechanism rather than graded good/bad: an administrator
 * reading "Web server (nginx)" can check it against what they configured,
 * which "Optimized" would not let them do.
 */
function deliveryLabel(method: FileDelivery['method'], t: (key: string) => string): string {
    if (method === 'nginx') return t('Web server (nginx)');
    if (method === 'xsendfile') return t('Web server (X-Sendfile)');

    return t('PHP');
}

/**
 * Where the uploaded files actually live, when that is worth saying.
 *
 * Two different risks, deliberately given two different weights. Files on
 * the container's own filesystem are already lost — the next rebuild is
 * simply when the operator finds out — so that is a red alert. Files in a
 * Docker named volume are genuinely safe across upgrades, and calling that
 * an emergency would teach people to ignore this box; it is an advisory
 * about the two things that do take a named volume down, both of which are
 * one command typed in a hurry.
 */
function StorageDurabilityNotice({ durability }: { durability: StorageDurability }) {
    const { t } = useTranslation();

    if (durability.level === 'ephemeral') {
        return (
            <Alert variant="destructive" className="mb-3">
                <AlertTriangle className="size-4" />
                <AlertTitle>{t('Uploaded files are stored inside the container')}</AlertTitle>
                <AlertDescription>
                    <p>
                        {t(
                            'They are not on any volume, so rebuilding or recreating this container will permanently delete every file. Move them to a directory on the host before uploading anything else.',
                        )}
                    </p>
                </AlertDescription>
            </Alert>
        );
    }

    if (durability.level === 'docker_volume') {
        return (
            <Alert variant="warning" className="mb-3">
                <HardDrive className="size-4" />
                <AlertTitle>{t('Uploaded files are inside Docker')}</AlertTitle>
                <AlertDescription>
                    <p>
                        {durability.volume
                            ? t('They survive upgrades, but they live in the Docker volume :volume rather than a directory you chose.', {
                                  volume: durability.volume,
                              })
                            : t('They survive upgrades, but they live in a Docker-managed volume rather than a directory you chose.')}{' '}
                        {t(
                            'That means docker compose down -v and docker volume prune both delete them, and a backup of your server can miss them entirely.',
                        )}
                    </p>
                </AlertDescription>
            </Alert>
        );
    }

    return null;
}

/**
 * What the scanning row says, and whether it is a warning.
 *
 * Four states in one line, because the row is always there: no scanner at
 * all, one that is not answering, one letting files through, and one
 * quietly working — which is the common case and the only one that is not
 * a warning.
 */
function scanningRow(
    scanning: NonNullable<SystemInfo['scanning']>,
    t: (key: string, replacements?: Record<string, string | number>) => string,
): { value: string; warning: boolean; title: string } {
    if (!scanning.configured) {
        return {
            value: t('Nothing'),
            warning: true,
            title: t('Uploads are passed on without being checked for viruses.'),
        };
    }

    const engine = scanning.engine ?? t('A virus scanner');

    if (!scanning.reachable) {
        return { value: t(':engine (not answering)', { engine }), warning: true, title: t('The scanner could not be reached.') };
    }

    if (scanning.let_through_24h > 0 || scanning.pending > 0) {
        return {
            value: engine,
            warning: true,
            title: t('Some files were not checked. Open the virus scanning settings for the detail.'),
        };
    }

    return { value: engine, warning: false, title: '' };
}

export function SystemWidget({ system, onViewReleaseNotes }: { system: SystemInfo; onViewReleaseNotes: () => void }) {
    const { t } = useTranslation();
    const { update_notice } = usePage<SharedData>().props;
    const durability = system.storage_durability;
    const [deliveryOpen, setDeliveryOpen] = useState(false);
    // Only PHP is worth flagging. The other two are the file being handed
    // to the web server, which is the outcome this is watching for.
    const deliveryNeedsAttention = system.file_delivery.method === 'php';
    const scanning = system.scanning ? scanningRow(system.scanning, t) : null;

    return (
        <div>
            {/* Before the update notice on purpose: losing the files outranks
                being a version behind. */}
            {durability && <StorageDurabilityNotice durability={durability} />}
            {/* Only for a scanner that is configured and misbehaving. An
                installation with no scanner at all says so on its own row
                below, with the same link — two warnings for one fact would
                make the card noisier without saying more. */}
            {system.missing_files > 0 && (
                <Alert variant="destructive" className="mb-3">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>{t(':count files are missing from storage', { count: system.missing_files })}</AlertTitle>
                    <AlertDescription>
                        {t('They are listed in the library and cannot be downloaded. Their bytes are not where this installation expects them.')}
                        <Link href="/files/orphans?tab=missing" className="mt-1 inline-block underline hover:no-underline">
                            {t('See which files')}
                        </Link>
                    </AlertDescription>
                </Alert>
            )}
            {system.scanning?.configured && scanning?.warning && (
                <Alert variant="warning" className="mb-3">
                    <ShieldAlert className="size-4" />
                    <AlertTitle>
                        {system.scanning.reachable ? t('Files are going out unscanned') : t('The virus scanner is not answering')}
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-inside list-disc">
                            {!system.scanning.reachable && <li>{t('Uploads cannot be checked until it is back.')}</li>}
                            {system.scanning.let_through_24h > 0 && (
                                <li>
                                    {t(':count files were allowed through without being scanned in the last 24 hours.', {
                                        count: system.scanning.let_through_24h,
                                    })}
                                </li>
                            )}
                            {system.scanning.pending > 0 && (
                                <li>{t(':count files have been waiting to be checked for over an hour.', { count: system.scanning.pending })}</li>
                            )}
                            {system.scanning.definitions_age_hours !== null && system.scanning.definitions_age_hours >= 72 && (
                                <li>{t('The virus definitions are :hours hours old.', { hours: system.scanning.definitions_age_hours })}</li>
                            )}
                        </ul>
                        <Link href="/system/settings/virus-scanning" className="mt-1 inline-block underline hover:no-underline">
                            {t('Virus scanning settings')}
                        </Link>
                    </AlertDescription>
                </Alert>
            )}
            {system.update_available && (
                <Alert variant="warning" className="mb-3">
                    <ArrowUpCircle className="size-4" />
                    <AlertTitle>{t('ProjectSend :version is available', { version: system.latest_version ?? '' })}</AlertTitle>
                    <AlertDescription>
                        <UpdateInstructions kind={system.install_kind} compact codeClassName="bg-amber-100 dark:bg-amber-900/50" />
                        {update_notice ? (
                            <button type="button" onClick={onViewReleaseNotes} className="mt-1 inline-block underline hover:no-underline">
                                {t('View release notes')}
                            </button>
                        ) : (
                            system.release_url && (
                                <a
                                    href={system.release_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-1 inline-block underline hover:no-underline"
                                >
                                    {t('View release notes')}
                                </a>
                            )
                        )}
                    </AlertDescription>
                </Alert>
            )}
            <dl className="space-y-2 text-sm">
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t('Version')}</dt>
                    <dd>{system.version}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t('Edition')}</dt>
                    <dd className="capitalize">{system.edition}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">PHP</dt>
                    <dd>{system.php}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">Laravel</dt>
                    <dd>{system.laravel}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t('Database')}</dt>
                    <dd className="truncate">{system.database}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t('Storage used')}</dt>
                    <dd>{formatBytes(system.storage_used_bytes)}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t('File storage')}</dt>
                    <dd>
                        {system.storage_driver === 'local'
                            ? t('Local disk')
                            : system.storage_driver === 's3'
                              ? t('S3-compatible storage')
                              : t('External storage')}
                    </dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t('Storage available')}</dt>
                    <dd>
                        {system.storage_driver !== 'local'
                            ? t('Managed by provider')
                            : system.storage_free_bytes >= 0
                              ? formatBytes(system.storage_free_bytes)
                              : t('Unknown')}
                    </dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t('Temporary upload space')}</dt>
                    <dd>{system.upload_temp_free_bytes >= 0 ? formatBytes(system.upload_temp_free_bytes) : t('Unknown')}</dd>
                </div>
                {/* Stated always, flagged only when it is the slow one.
                    Both halves of the row open the explanation, and the
                    label is underlined, because the icon alone did not read
                    as clickable — the one thing an administrator has to do
                    here is find out what it means, and an affordance nobody
                    recognises is the same as not having one. Two buttons
                    rather than one wrapping the whole row: a dt/dd pair
                    cannot be nested inside a single button without losing
                    the description-list semantics that name the value. */}
                <div className="flex justify-between gap-2">
                    <dt>
                        {deliveryNeedsAttention ? (
                            <button
                                type="button"
                                onClick={() => setDeliveryOpen(true)}
                                className="font-medium text-amber-600 underline underline-offset-2 hover:no-underline dark:text-amber-500"
                                title={t('Downloads are not being handed to the web server')}
                            >
                                {t('Downloads sent by')}
                            </button>
                        ) : (
                            <span className="text-muted-foreground">{t('Downloads sent by')}</span>
                        )}
                    </dt>
                    <dd>
                        {deliveryNeedsAttention ? (
                            <button
                                type="button"
                                onClick={() => setDeliveryOpen(true)}
                                className="flex items-center gap-1.5 font-medium text-amber-600 hover:underline dark:text-amber-500"
                                // "PHP, button" is not a description of
                                // anything on its own.
                                aria-label={t('Downloads are being sent by PHP — why this is not optimal')}
                                title={t('Downloads are not being handed to the web server')}
                            >
                                {deliveryLabel(system.file_delivery.method, t)}
                                <AlertTriangle className="size-4" />
                            </button>
                        ) : (
                            <span>{deliveryLabel(system.file_delivery.method, t)}</span>
                        )}
                    </dd>
                </div>
                {/* Same rule as the row above, and the reason this one is
                    never hidden: an installation checking nothing looks
                    exactly like one that is. Absent only where the scanner
                    is not this installation's to connect. */}
                {scanning && (
                    <div className="flex justify-between gap-2">
                        <dt>
                            {scanning.warning ? (
                                <Link
                                    href="/system/settings/virus-scanning"
                                    className="font-medium text-amber-600 underline underline-offset-2 hover:no-underline dark:text-amber-500"
                                    title={scanning.title}
                                >
                                    {t('Uploads checked by')}
                                </Link>
                            ) : (
                                <span className="text-muted-foreground">{t('Uploads checked by')}</span>
                            )}
                        </dt>
                        <dd>
                            {scanning.warning ? (
                                <Link
                                    href="/system/settings/virus-scanning"
                                    className="flex items-center gap-1.5 font-medium text-amber-600 hover:underline dark:text-amber-500"
                                    aria-label={scanning.title}
                                    title={scanning.title}
                                >
                                    {scanning.value}
                                    <AlertTriangle className="size-4" />
                                </Link>
                            ) : (
                                <span>{scanning.value}</span>
                            )}
                        </dd>
                    </div>
                )}
                {/* Stated even when everything is correct: "my files are on a
                    host directory" is worth being able to confirm at a glance,
                    not only worth warning about when it is false. */}
                {durability && durability.level !== 'unknown' && (
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">{t('Files stored on')}</dt>
                        <dd className="truncate" title={durability.source ?? durability.volume ?? undefined}>
                            {durability.level === 'durable' && t('Host directory')}
                            {durability.level === 'docker_volume' && t('Docker volume')}
                            {durability.level === 'ephemeral' && t('Container filesystem')}
                        </dd>
                    </div>
                )}
            </dl>
            <p className="text-muted-foreground text-xs">
                {t('Uploads use temporary space while being assembled, including when files are stored externally.')}
            </p>

            <FileDeliveryDialog delivery={system.file_delivery} open={deliveryOpen} onOpenChange={setDeliveryOpen} />
        </div>
    );
}

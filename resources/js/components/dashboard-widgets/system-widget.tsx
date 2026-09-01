import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowUpCircle, HardDrive } from 'lucide-react';
import { useState } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { FileDeliveryDialog, type FileDelivery } from '@/components/file-delivery-dialog';
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
                        {t('That means docker compose down -v and docker volume prune both delete them, and a backup of your server can miss them entirely.')}
                    </p>
                </AlertDescription>
            </Alert>
        );
    }

    return null;
}

export function SystemWidget({ system, onViewReleaseNotes }: { system: SystemInfo; onViewReleaseNotes: () => void }) {
    const { t } = useTranslation();
    const { update_notice } = usePage<SharedData>().props;
    const durability = system.storage_durability;
    const [deliveryOpen, setDeliveryOpen] = useState(false);
    // Only PHP is worth flagging. The other two are the file being handed
    // to the web server, which is the outcome this is watching for.
    const deliveryNeedsAttention = system.file_delivery.method === 'php';

    return (
        <div>
            {/* Before the update notice on purpose: losing the files outranks
                being a version behind. */}
            {durability && <StorageDurabilityNotice durability={durability} />}
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
                {system.storage_free_bytes >= 0 && (
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">{t('Storage free')}</dt>
                        <dd>{formatBytes(system.storage_free_bytes)}</dd>
                    </div>
                )}
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
            <FileDeliveryDialog delivery={system.file_delivery} open={deliveryOpen} onOpenChange={setDeliveryOpen} />
        </div>
    );
}

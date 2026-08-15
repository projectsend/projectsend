import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CircleCheck, MessagesSquare } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface ReleaseItem {
    /** The bold lead-in of a changelog bullet; empty when it has none. */
    title: string;
    body: string;
}

interface ReleaseGroup {
    /** "Added", "Improved", … — empty for a list written without one. */
    heading: string;
    items: ReleaseItem[];
}

interface Release {
    version: string;
    date: string;
    intro: string[];
    groups: ReleaseGroup[];
}

interface WhatsNewProps {
    version: string;
    /** Empty when the previous version was never recorded. */
    previousVersion: string;
    /** False when the page is opened from a link rather than after an update. */
    justUpdated: boolean;
    releases: Release[];
}

export default function WhatsNew({ version, previousVersion, justUpdated, releases }: WhatsNewProps) {
    const { t } = useTranslation();
    const { links } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [{ title: t("What's new"), href: '/system/whats-new' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t("What's new")} />

            <div className="px-4 py-6">
                <div className="mx-auto max-w-2xl space-y-10">
                    <div className="space-y-3 text-center">
                        <CircleCheck className="text-primary mx-auto size-12" strokeWidth={1.5} />

                        <h1 className="text-2xl font-semibold tracking-tight">
                            {justUpdated ? t('ProjectSend is now on :version', { version }) : t('ProjectSend :version', { version })}
                        </h1>

                        <p className="text-muted-foreground text-sm">
                            {justUpdated
                                ? previousVersion !== ''
                                    ? t('The update from :previous finished, and everything came back up. Thank you for keeping it current.', {
                                          previous: previousVersion,
                                      })
                                    : t('The update finished, and everything came back up. Thank you for keeping it current.')
                                : t('What each release brought to this installation.')}
                        </p>
                    </div>

                    {/* The invitation, above the notes on purpose: it is the
                        part with a person on the other end of it. */}
                    <div className="bg-muted/40 flex flex-col items-center gap-4 rounded-lg border p-6 text-center">
                        <MessagesSquare className="text-muted-foreground size-8" strokeWidth={1.5} />

                        <div className="space-y-1">
                            <p className="font-medium">{t('Come and say hello')}</p>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'ProjectSend has a Discord: release news, help when something is not behaving, and other people running the same software. We are in it too.',
                                )}
                            </p>
                        </div>

                        <Button asChild variant="outline">
                            <a href={links.discord} target="_blank" rel="noreferrer">
                                {t('Join the Discord')}
                            </a>
                        </Button>
                    </div>

                    {releases.length > 0 && (
                        <div className="space-y-8">
                            <h2 className="text-lg font-semibold tracking-tight">{t("What's new")}</h2>

                            {releases.map((release) => (
                                <ReleaseNotes key={release.version} release={release} />
                            ))}
                        </div>
                    )}

                    <div className="flex justify-center">
                        <Button asChild>
                            <Link href={route('dashboard')}>
                                {t('Continue to the dashboard')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function ReleaseNotes({ release }: { release: Release }) {
    return (
        <section className="space-y-4">
            <div className="flex items-baseline gap-3 border-b pb-2">
                <h3 className="font-semibold">{release.version}</h3>
                {release.date !== '' && <span className="text-muted-foreground text-sm">{release.date}</span>}
            </div>

            {release.intro.map((paragraph, index) => (
                <p key={index} className="text-muted-foreground text-sm">
                    {paragraph}
                </p>
            ))}

            {release.groups.map((group, index) => (
                <div key={index} className="space-y-3">
                    {group.heading !== '' && <h4 className="text-sm font-medium">{group.heading}</h4>}

                    <ul className="space-y-3">
                        {group.items.map((item, itemIndex) => (
                            <li key={itemIndex} className="text-sm">
                                {item.title !== '' && <span className="font-medium">{item.title}. </span>}
                                <span className="text-muted-foreground">{item.body}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </section>
    );
}

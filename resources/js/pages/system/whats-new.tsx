import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck } from 'lucide-react';

import DiscordInvitation from '@/components/discord-invitation';
import MadeInArgentina from '@/components/made-in-argentina';
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

    const breadcrumbs: BreadcrumbItem[] = [{ title: t("What's new"), href: '/system/whats-new' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t("What's new")} />

            <div className="px-4 py-6">
                <div className="mx-auto max-w-2xl space-y-10">
                    <div className="space-y-3 text-center">
                        <CircleCheck className="text-primary mx-auto size-12" strokeWidth={1.5} />

                        <h1 className="text-2xl font-semibold tracking-tight">
                            {justUpdated ? t('Thank you for updating ProjectSend') : t('ProjectSend :version', { version })}
                        </h1>

                        {/* Warm while it is a greeting, plain once it is a
                            reference: somebody who opened this from a link
                            months later is looking something up, and being
                            thanked again would be noise. */}
                        <p className="text-muted-foreground text-sm">
                            {justUpdated
                                ? previousVersion !== ''
                                    ? t(
                                          'You are now on :version, up from :previous, and everything came back up. Thank you for continuing to trust ProjectSend with your file sharing.',
                                          { version, previous: previousVersion },
                                      )
                                    : t(
                                          'You are now on :version, and everything came back up. Thank you for continuing to trust ProjectSend with your file sharing.',
                                          { version },
                                      )
                                : t('What each release brought to this installation.')}
                        </p>
                    </div>

                    {/* The invitation, above the notes on purpose: it is the
                        part with a person on the other end of it. */}
                    <DiscordInvitation />

                    {releases.length > 0 && (
                        <div className="space-y-8">
                            <h2 className="text-lg font-semibold tracking-tight">{t("What's new")}</h2>

                            {releases.map((release) => (
                                <ReleaseNotes key={release.version} release={release} />
                            ))}
                        </div>
                    )}

                    <div className="space-y-6">
                        {/* Outline, so the invitation above is the one filled
                            button on the page. Leaving is not the thing being
                            encouraged here. */}
                        <div className="flex justify-center">
                            <Button asChild variant="outline">
                                <Link href={route('dashboard')}>
                                    {t('Continue to the dashboard')}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </div>

                        <MadeInArgentina />
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

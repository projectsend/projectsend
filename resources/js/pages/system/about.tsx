import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

import Heading from '@/components/heading';
import ProjectSendLogo from '@/components/projectsend-logo';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface Environment {
    version: string;
    edition: string;
    php: string;
    laravel: string;
    database: string;
}

interface LastUpdate {
    version: string;
    at: string;
}

interface AboutProps {
    license: string;
    /** Null for anyone the dashboard's System widget would also hide it from. */
    environment: Environment | null;
    /** Null on an installation that has never been updated through the command. */
    updated: LastUpdate | null;
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4 py-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right break-all">{value}</dd>
        </div>
    );
}

export default function About({ license, environment, updated }: AboutProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const { version, edition, links } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('About'), href: '/system/about' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('About')} />

            <div className="px-4 py-6">
                <Heading title={t('About')} description={t('The release this installation is running, and where it comes from.')} />

                <div className="max-w-xl space-y-8">
                    <div className="flex items-center gap-4">
                        <ProjectSendLogo className="text-foreground h-12 w-auto" />
                        <div>
                            <p className="text-lg font-medium">ProjectSend</p>
                            <p className="text-muted-foreground text-sm">
                                {t('Version :version', { version })} · <span className="capitalize">{edition}</span>
                            </p>
                        </div>
                    </div>

                    <p className="text-muted-foreground text-sm">
                        {t(
                            'ProjectSend is free, open source software for sending files to your clients. It is released under the :license, which means you are free to use, study, change and share it.',
                            { license },
                        )}
                    </p>

                    <div className="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                        <a href={links.website} target="_blank" rel="noreferrer" className="underline hover:no-underline">
                            {t('Website')}
                        </a>
                        <a href={links.source} target="_blank" rel="noreferrer" className="underline hover:no-underline">
                            {t('Source code')}
                        </a>
                        {/* Absent on a managed installation, where the reader
                            is already paying for this — see OfficialLinks. */}
                        {links.open_collective && (
                            <a href={links.open_collective} target="_blank" rel="noreferrer" className="underline hover:no-underline">
                                {t('Support the project')}
                            </a>
                        )}
                        <a href={links.discord} target="_blank" rel="noreferrer" className="underline hover:no-underline">
                            {t('Discord')}
                        </a>
                        <Link href={route('system.getting-started')} className="underline hover:no-underline">
                            {t('Getting started')}
                        </Link>
                        {/* Same gate as the environment block below, which is
                            why it rides on the same prop: the page it links to
                            answers the question this one starts. */}
                        {environment && (
                            <Link href={route('system.whats-new')} className="underline hover:no-underline">
                                {t("What's new")}
                            </Link>
                        )}
                    </div>

                    {environment && (
                        <div>
                            <h2 className="mb-1 text-sm font-medium">{t('Environment')}</h2>
                            <p className="text-muted-foreground mb-2 text-sm">
                                {t('Worth including when you report a problem.')}
                            </p>
                            <dl className="divide-y text-sm">
                                <Row label={t('Version')} value={environment.version} />
                                {/* Absent until this installation has been
                                    updated at least once through the command —
                                    a fresh install has no update to date. */}
                                {updated && <Row label={t('Updated')} value={t(':version on :date', { version: updated.version, date: dateTime(updated.at) })} />}
                                <Row label={t('Edition')} value={environment.edition} />
                                <Row label="PHP" value={environment.php} />
                                <Row label="Laravel" value={environment.laravel} />
                                <Row label={t('Database')} value={environment.database} />
                            </dl>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

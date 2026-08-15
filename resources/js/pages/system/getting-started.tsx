import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, MessagesSquare } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface QuickStartItem {
    key: string;
    title: string;
    description: string;
    href: string;
    /** True only where the database can answer it — see QuickStart. */
    done: boolean;
}

interface GettingStartedProps {
    items: QuickStartItem[];
    /** False once the page has been read, or when opened from a link later. */
    justInstalled: boolean;
}

export default function GettingStarted({ items, justInstalled }: GettingStartedProps) {
    const { t } = useTranslation();
    const { name, links } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Getting started'), href: '/system/getting-started' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Getting started')} />

            <div className="px-4 py-6">
                <div className="mx-auto max-w-2xl space-y-10">
                    <div className="space-y-3 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {justInstalled ? t('Welcome to ProjectSend') : t('Getting started')}
                        </h1>

                        <p className="text-muted-foreground text-sm">
                            {justInstalled
                                ? t(
                                      ':name is installed and yours. Thank you for choosing software you can run yourself — here is the short version of what to do next.',
                                      { name },
                                  )
                                : t('The short version of what to do on a new installation. Nothing here expires.')}
                        </p>
                    </div>

                    <ol className="space-y-3">
                        {items.map((item, index) => (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    className="hover:border-primary/40 hover:bg-accent/40 group flex items-start gap-4 rounded-lg border p-4 transition-colors"
                                >
                                    {/* The step number becomes a tick where the
                                        database can actually answer whether it
                                        has been done. */}
                                    <span
                                        className={`flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-medium ${
                                            item.done ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {item.done ? <Check className="size-4" strokeWidth={3} /> : index + 1}
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className={`block font-medium ${item.done ? 'text-muted-foreground line-through' : ''}`}>
                                            {item.title}
                                        </span>
                                        <span className="text-muted-foreground block text-sm">{item.description}</span>
                                    </span>

                                    <ArrowRight className="text-muted-foreground/50 group-hover:text-primary mt-0.5 size-4 shrink-0" />
                                </Link>
                            </li>
                        ))}
                    </ol>

                    {/* Last, deliberately. Somebody who has just installed this
                        came here with a job in mind, and the fastest way to
                        lose them is to lead with a social invitation. It is
                        still worth making — after the work, not in front of
                        it. */}
                    <div className="bg-accent border-primary/20 flex flex-col items-center gap-4 rounded-lg border p-6 text-center">
                        <MessagesSquare className="text-primary size-8" strokeWidth={1.5} />

                        <div className="space-y-1">
                            <p className="text-accent-foreground font-medium">{t('Come and say hello')}</p>
                            <p className="text-accent-foreground/80 text-sm">
                                {t(
                                    'ProjectSend has a Discord: release news, help when something is not behaving, and other people running the same software. We are in it too.',
                                )}
                            </p>
                        </div>

                        <Button asChild>
                            <a href={links.discord} target="_blank" rel="noreferrer">
                                {t('Join the Discord')}
                            </a>
                        </Button>
                    </div>

                    <div className="flex justify-center">
                        <Button asChild variant="outline">
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

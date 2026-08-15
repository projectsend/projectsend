import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Boxes, Check, Clock, Contact, MailOpen, Palette, Send, Upload, Users, type LucideIcon } from 'lucide-react';

import DiscordInvitation from '@/components/discord-invitation';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface QuickStartItem {
    key: string;
    title: string;
    description: string;
    href: string;
    /** The installation does not really work without this one. */
    essential: boolean;
    /** True only where the database can answer it — see QuickStart. */
    done: boolean;
}

interface GettingStartedProps {
    items: QuickStartItem[];
    /** False once the page has been read, or when opened from a link later. */
    justInstalled: boolean;
}

/**
 * Borrowed from the sidebar's own vocabulary wherever there is one, so
 * the icon on a card is the icon on the screen it opens — see
 * app-sidebar.tsx. Icons are presentation, so they are chosen here rather
 * than sent over the wire by QuickStart.
 */
const ICONS: Record<string, LucideIcon> = {
    client: Contact,
    upload: Upload,
    group: Boxes,
    theme: Palette,
    'email-theme': MailOpen,
    email: Send,
    team: Users,
    scheduler: Clock,
};

export default function GettingStarted({ items, justInstalled }: GettingStartedProps) {
    const { t } = useTranslation();
    const { version } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Getting started'), href: '/system/getting-started' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Getting started')} />

            <div className="px-4 py-6">
                <div className="mx-auto max-w-3xl space-y-8">
                    <div className="space-y-2 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {justInstalled ? t('Thank you for installing ProjectSend') : t('Getting started')}
                        </h1>

                        <p className="text-muted-foreground text-sm">
                            {justInstalled
                                ? t('You are running version :version, and it is yours to keep. Here is what is worth doing first.', {
                                      version,
                                  })
                                : t('The short version of what to do on a new installation. Nothing here expires.')}
                        </p>
                    </div>

                    {/* Two columns, so eight steps are four rows and the whole
                        page is one screen. One column on a phone. */}
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {items.map((item) => (
                            <li key={item.key}>
                                <QuickStartCard item={item} />
                            </li>
                        ))}
                    </ul>

                    {/* Last, deliberately. Somebody who has just installed this
                        came here with a job in mind, and the fastest way to
                        lose them is to lead with a social invitation. It is
                        still worth making — after the work, not in front of
                        it. */}
                    <DiscordInvitation />

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

function QuickStartCard({ item }: { item: QuickStartItem }) {
    const { t } = useTranslation();
    const Icon = ICONS[item.key] ?? ArrowRight;

    // Three states, each on a colour this application already uses for
    // exactly this meaning: green for done (the --success token behind
    // Badge's success variant), amber for "attend to this" (the warning
    // Alert variant, verbatim), and the brand colour for everything else.
    // Amber rather than red throughout: none of these is broken, one of
    // them is just more urgent than the others.
    const chip = item.done
        ? 'bg-success/10 text-success'
        : item.essential
          ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-500'
          : 'bg-accent text-primary';

    return (
        <Link
            href={item.href}
            className="hover:border-primary/40 hover:bg-accent/40 group flex h-full items-start gap-3 rounded-lg border p-4 transition-colors"
        >
            <span className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${chip}`}>
                {item.done ? <Check className="size-4" strokeWidth={3} /> : <Icon className="size-4" strokeWidth={2} />}
            </span>

            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className={`font-medium ${item.done ? 'text-muted-foreground line-through' : ''}`}>{item.title}</span>

                    {item.essential && !item.done && (
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-700 uppercase dark:bg-amber-900/40 dark:text-amber-500">
                            {t('Essential')}
                        </span>
                    )}
                </span>

                <span className="text-muted-foreground block text-sm">{item.description}</span>
            </span>

            <ArrowRight className="text-muted-foreground/40 group-hover:text-primary mt-1 size-4 shrink-0" />
        </Link>
    );
}

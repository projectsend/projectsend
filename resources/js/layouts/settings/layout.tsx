import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const { t } = useTranslation();
    const { auth, social_login } = usePage<SharedData>().props;
    const currentPath = window.location.pathname;

    const sidebarNavItems: NavItem[] = [
        {
            title: t('Profile'),
            url: '/settings/profile',
            icon: null,
        },
        {
            title: t('Password'),
            url: '/settings/password',
            icon: null,
        },
        {
            title: t('Two-factor auth'),
            url: '/settings/two-factor',
            icon: null,
        },
        {
            title: t('Notifications'),
            url: '/settings/notifications',
            icon: null,
        },
        // Hidden when no provider is configured, which is the default —
        // an entry leading to an empty screen is worse than no entry.
        ...(social_login.length > 0
            ? [
                  {
                      title: t('Connected accounts'),
                      url: '/settings/connected-accounts',
                      icon: null,
                  },
              ]
            : []),
        // Matches the route's own `staff` middleware — the API is staff-only,
        // so a client would only ever see this entry 403.
        ...(auth.user.type === 'staff'
            ? [
                  {
                      title: t('API tokens'),
                      url: '/settings/api-tokens',
                      icon: null,
                  },
              ]
            : []),
    ];

    return (
        <div className="px-4 py-6">
            <Heading title={t('Settings')} description={t('Manage your profile and account settings')} />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.url,
                                })}
                            >
                                <Link href={item.url ?? '#'}>{item.title}</Link>
                            </Button>
                        ))}

                        {/* Set apart from the rest: a rule above it and a
                            muted red, so it reads as the end of the list and
                            as consequential without shouting. Deliberately
                            not `variant="destructive"` — a filled red button
                            in a nav would pull the eye every time this page
                            loads, and nobody comes to Settings to delete
                            their account. */}
                        <Separator className="my-2" />

                        <Button
                            size="sm"
                            variant="ghost"
                            asChild
                            className={cn(
                                'w-full justify-start text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/30 dark:hover:text-red-300',
                                {
                                    'bg-red-50 dark:bg-red-950/30': currentPath === '/settings/delete-account',
                                },
                            )}
                        >
                            <Link href="/settings/delete-account">{t('Delete account')}</Link>
                        </Button>
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}

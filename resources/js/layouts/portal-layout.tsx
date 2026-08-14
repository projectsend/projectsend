import AppLogoIcon from '@/components/app-logo-icon';
import { AppearanceSwitcher } from '@/components/appearance-switcher';
import { IconLocaleSwitcher } from '@/components/locale-switcher';
import { NotificationBell } from '@/components/notification-bell';
import { PoweredBy } from '@/components/powered-by';
import { TimezoneDetector } from '@/components/timezone-detector';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useTranslation } from '@/hooks/use-translation';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

interface PortalLayoutProps {
    children: React.ReactNode;
}

/**
 * The client portal's own shell — unlike the rest of the app (which
 * reused the staff AppLayout/sidebar wholesale until now), this is a
 * dedicated client-facing header: logo/site name, a couple of nav links,
 * and the account menu, no admin sidebar at all.
 *
 * Shared unmodified by the "Compact" and "Drive" portal themes — what
 * separates those two lives in the pages and their toolbar/comment
 * shells, not here. "Default" uses the staff AppLayout instead, and
 * "Gallery" has portal-layout-gallery.tsx.
 */
export default function PortalLayout({ children }: PortalLayoutProps) {
    const { t } = useTranslation();
    const { auth, name, branding } = usePage<SharedData>().props;
    const canUpload = auth.permissions.includes('upload');

    return (
        <div className="bg-background flex min-h-svh flex-col">
            <header className="flex items-center justify-between border-b px-4 py-3 md:px-8">
                <div className="flex items-center gap-6">
                    <Link href={route('home')} className="flex items-center gap-2 font-medium">
                        {branding?.logo_url ? (
                            <img src={branding.logo_url} alt={name} className="size-7 object-contain" />
                        ) : (
                            <AppLogoIcon className="text-foreground size-7" />
                        )}
                        <span className="text-foreground text-sm font-semibold">{name}</span>
                    </Link>
                    <nav className="flex items-center gap-4 text-sm">
                        <Link href={route('my-files.index')} className="text-muted-foreground hover:text-foreground">
                            {t('My files')}
                        </Link>
                        <Link href={route('my-groups.index')} className="text-muted-foreground hover:text-foreground">
                            {t('My groups')}
                        </Link>
                        {canUpload && (
                            <Link href={route('my-files.upload.create')} className="text-muted-foreground hover:text-foreground">
                                {t('Upload')}
                            </Link>
                        )}
                    </nav>
                </div>

                <div className="flex items-center gap-1">
                    <NotificationBell />
                    <AppearanceSwitcher />
                    <IconLocaleSwitcher />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="flex items-center gap-2 px-2">
                                <UserInfo user={auth.user} />
                                <ChevronDown className="text-muted-foreground size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="min-w-56 rounded-lg">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t px-4 py-3 text-center md:px-8">
                <PoweredBy className="text-muted-foreground/60 hover:text-muted-foreground text-xs transition-colors" />
            </footer>

            <TimezoneDetector />
        </div>
    );
}

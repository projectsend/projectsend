import { AppearanceSwitcher } from '@/components/appearance-switcher';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { IconLocaleSwitcher } from '@/components/locale-switcher';
import { NotificationBell } from '@/components/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UpdateAvailableIcon } from '@/components/update-available-icon';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex items-center gap-1">
                <UpdateAvailableIcon />
                <NotificationBell />
                <AppearanceSwitcher />
                <IconLocaleSwitcher />
            </div>
        </header>
    );
}

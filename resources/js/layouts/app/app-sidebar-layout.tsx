import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { CodeNoticeBanner } from '@/components/code-notice-banner';
import { WorkerNoticeBanner } from '@/components/worker-notice-banner';
import { Toaster } from '@/components/toaster';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {/* Renders nothing unless this process is running code the
                    installation was not updated to — see RunningCodeState.
                    Here rather than on the dashboard because the person it
                    is for has no reason to go looking. */}
                <div className="px-6 pt-4 empty:hidden md:px-4">
                    <CodeNoticeBanner />
                    <WorkerNoticeBanner />
                </div>
                {children}
            </AppContent>
            <Toaster />
        </AppShell>
    );
}

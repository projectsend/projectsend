import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { PoweredBy } from '@/components/powered-by';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useTranslation } from '@/hooks/use-translation';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    BookOpen,
    Boxes,
    Contact,
    Download,
    FileCode,
    FileText,
    FileWarning,
    History,
    KeyRound,
    LayoutGrid,
    ListChecks,
    MessageSquare,
    Settings,
    ShieldCheck,
    Tags,
    Upload,
    UserCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { t } = useTranslation();
    const { auth, capabilities, pending, version } = usePage<SharedData>().props;

    // Modules (Files, Clients, Groups…) add their own groups here as
    // they land, mirroring v1's grouped admin menu.
    const groups: NavGroup[] = [
        {
            title: t('Platform'),
            items: [
                {
                    title: t('Dashboard'),
                    url: '/dashboard',
                    icon: LayoutGrid,
                },
            ],
        },
    ];

    // Visibility follows the user's granted permissions; the backend
    // gates (staff + can: middleware) are the real enforcement. Staff
    // surfaces never appear for clients regardless of granted keys.
    const isStaff = auth.user.type === 'staff';
    const can = (permission: string) => isStaff && auth.permissions.includes(permission);

    if (auth.user.type === 'client') {
        groups[0].items.push({
            title: t('My files'),
            url: '/my-files',
            icon: FileText,
        });
        if (auth.permissions.includes('upload')) {
            groups[0].items.push({
                title: t('Upload'),
                url: '/my-files/upload',
                icon: Upload,
            });
        }
        groups[0].items.push({
            title: t('My groups'),
            url: '/my-groups',
            icon: Boxes,
        });
    }

    const fileItems: NavGroup['items'] = [];
    if (can('upload')) {
        fileItems.push({ title: t('All files'), url: '/files', icon: FileText });
        fileItems.push({ title: t('Upload'), url: '/files/upload', icon: Upload });
    }
    if (can('create_categories') || can('edit_categories') || can('delete_categories')) {
        fileItems.push({ title: t('Categories'), url: '/categories', icon: Tags });
    }
    if (can('import_orphans')) {
        fileItems.push({ title: t('Import orphan files'), url: '/files/orphans', icon: FileWarning });
    }
    if (can('moderate_comments')) {
        // Just "Comments" — the old "Comments awaiting approval" wrapped
        // and pushed its own count badge out of the sidebar, and the screen
        // is no longer only about approving.
        fileItems.push({ title: t('Comments'), url: '/comments', icon: MessageSquare, badge: pending.comments });
    }
    // Sits with the files rather than under Administration: it answers
    // "who took this file", which is a question about the library, not
    // about the installation. Still gated on view_actions_log — moving
    // where it appears must not change who may see it.
    if (can('view_actions_log')) {
        fileItems.push({ title: t('Download history'), url: '/downloads', icon: Download });
    }
    if (fileItems.length > 0) {
        groups.push({ title: t('Files'), items: fileItems });
    }

    const clientItems: NavGroup['items'] = [];

    if (can('manage_clients')) {
        clientItems.push({
            title: t('Clients'),
            url: '/clients',
            icon: Contact,
        });
    }

    if (can('manage_custom_fields')) {
        clientItems.push({
            title: t('Custom fields'),
            url: '/client-custom-fields',
            icon: ListChecks,
        });
    }

    if (can('manage_groups')) {
        clientItems.push({
            title: t('Groups'),
            url: '/groups',
            icon: Boxes,
        });
    }

    if (can('approve_account_requests')) {
        clientItems.push({
            title: t('Account requests'),
            url: '/account-requests',
            icon: UserPlus,
            badge: pending.account_requests,
        });
    }

    if (can('approve_groups_memberships_requests')) {
        clientItems.push({
            title: t('Membership requests'),
            url: '/membership-requests',
            icon: UserCheck,
            badge: pending.membership_requests,
        });
    }

    if (clientItems.length > 0) {
        groups.push({
            title: t('Clients'),
            items: clientItems,
        });
    }

    const adminItems: NavGroup['items'] = [];

    if (can('manage_users') && capabilities.includes('users.manage')) {
        adminItems.push({
            title: t('System users'),
            url: '/users',
            icon: Users,
        });
        adminItems.push({
            title: t('Roles'),
            url: '/roles',
            icon: ShieldCheck,
        });
        adminItems.push({
            title: t('Convert accounts'),
            url: '/users/convert',
            icon: ArrowLeftRight,
        });
    }

    if (can('view_actions_log')) {
        adminItems.push({
            title: t('Activity log'),
            url: '/activity',
            icon: History,
        });
    }

    if ((can('create_assets') || can('edit_assets') || can('delete_assets')) && capabilities.includes('custom_assets.manage')) {
        adminItems.push({
            title: t('Custom assets'),
            url: '/system/settings/custom-assets',
            icon: FileCode,
        });
    }

    const canCustomizeBranding = can('edit_settings') && capabilities.includes('branding.customize');

    // The API surface gets a group of its own rather than living under
    // Settings: tokens are per-account credentials and the dashboard is
    // usage, neither of which is configuration. Staff-only, since /api/v1
    // is staff-only.
    if (isStaff) {
        groups.push({
            title: t('API'),
            items: [
                { title: t('Dashboard'), url: '/api', icon: Activity },
                { title: t('Documentation'), url: '/api/docs', icon: BookOpen },
                { title: t('Tokens'), url: '/settings/api-tokens', icon: KeyRound },
            ],
        });
    }

    if (can('edit_settings') || can('edit_email_templates') || canCustomizeBranding) {
        const settings = can('edit_settings');

        // One ordered list grouped by subject, each entry carrying its own
        // condition. The conditional entries used to be pushed on at the end
        // regardless of what they were about, which is why Storage sat under
        // Languages and Email templates sat nowhere near Email.
        const settingsItems = [
            // The installation itself, and how it looks.
            { title: t('General'), url: '/system/settings/general', when: settings },
            { title: t('Languages'), url: '/system/settings/languages', when: settings },
            { title: t('Theming'), url: '/system/settings/theming', when: settings },
            { title: t('Branding'), url: '/system/settings/branding', when: canCustomizeBranding },

            // Who gets an account, and how they prove who they are.
            { title: t('Clients'), url: '/system/settings/clients', when: settings },
            { title: t('Security'), url: '/system/settings/security', when: settings },
            { title: t('LDAP'), url: '/system/settings/ldap', when: settings },
            { title: t('Social login'), url: '/system/settings/social-login', when: settings },
            { title: t('CAPTCHA'), url: '/system/settings/captcha', when: settings },

            // Files: where they land, how long they stay, who can see them.
            { title: t('Uploads'), url: '/system/settings/uploads', when: settings },
            { title: t('Storage'), url: '/system/settings/storage', when: settings && capabilities.includes('storage.configure') },
            { title: t('File retention'), url: '/system/settings/file-retention', when: settings },
            { title: t('Comments'), url: '/system/settings/comments', when: settings },
            { title: t('Public listing'), url: '/system/settings/public-listing', when: settings },
            { title: t('Privacy'), url: '/system/settings/privacy', when: settings },

            // Outgoing mail: the transport, then what it says.
            { title: t('Email'), url: '/system/settings/email', when: settings },
            { title: t('Email templates'), url: '/system/settings/email-templates', when: can('edit_email_templates') },

            // Housekeeping.
            { title: t('Scheduler'), url: '/system/settings/scheduler', when: settings && capabilities.includes('scheduler.monitoring') },
            { title: t('About'), url: '/system/about', when: settings },
        ]
            .filter((item) => item.when)
            .map(({ title, url }) => ({ title, url }));

        adminItems.push({
            title: t('Settings'),
            icon: Settings,
            items: settingsItems,
        });
    }

    if (adminItems.length > 0) {
        groups.push({
            title: t('Administration'),
            items: adminItems,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter className="mt-auto">
                <NavUser />

                {/* Staff get the exact release, linked to /system/about —
                    which is also how someone without edit_settings reaches
                    that page, since they never see the Settings submenu.
                    Clients reach this shell through the Default portal
                    theme, and get the version-less line every other
                    client-facing surface shows. Both hide when the sidebar
                    collapses to icons. */}
                <div className="px-2 pb-1 text-center group-data-[collapsible=icon]:hidden">
                    {isStaff ? (
                        <Link
                            href="/system/about"
                            className="text-sidebar-foreground/50 hover:text-sidebar-foreground/80 text-xs transition-colors"
                        >
                            ProjectSend {version}
                        </Link>
                    ) : (
                        <PoweredBy className="text-sidebar-foreground/50 hover:text-sidebar-foreground/80 text-xs transition-colors" />
                    )}
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}

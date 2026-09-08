import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, ExternalLink } from 'lucide-react';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const page = usePage();

    const isActive = (url?: string) => url !== undefined && (page.url === url || page.url.startsWith(url + '/'));

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-0">
                    <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) =>
                            item.items && item.items.length > 0 ? (
                                <Collapsible
                                    key={item.title}
                                    asChild
                                    defaultOpen={item.items.some((sub) => isActive(sub.url))}
                                    className="group/collapsible"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton tooltip={item.title}>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {item.items.map((sub) => (
                                                    <SidebarMenuSubItem key={sub.title}>
                                                        <SidebarMenuSubButton asChild isActive={isActive(sub.url)}>
                                                            <Link href={sub.url ?? '#'}>
                                                                <span>{sub.title}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            ) : (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={item.external ? false : isActive(item.url)}
                                        tooltip={item.title}
                                    >
                                        {/* An external destination is a plain
                                            anchor, never an Inertia <Link>:
                                            Link expects a page component back
                                            and another origin will not give it
                                            one, so it fails without saying so.
                                            It is also never "active" — nothing
                                            outside this app is the page you
                                            are on. */}
                                        {item.external ? (
                                            <a href={item.url ?? '#'} target="_blank" rel="noopener noreferrer">
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                                <ExternalLink className="ml-auto size-3.5 opacity-60" />
                                            </a>
                                        ) : (
                                            <Link href={item.url ?? '#'}>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        )}
                                    </SidebarMenuButton>
                                    {item.badge !== undefined && item.badge > 0 && (
                                        <SidebarMenuBadge className="bg-primary text-primary-foreground peer-hover/menu-button:text-primary-foreground peer-data-[active=true]/menu-button:text-primary-foreground rounded-full">
                                            {item.badge}
                                        </SidebarMenuBadge>
                                    )}
                                </SidebarMenuItem>
                            ),
                        )}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}

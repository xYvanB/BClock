import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type Role, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Clock, LayoutGrid, Layers, Users } from 'lucide-react';
import AppLogo from './app-logo';

interface RoleNavItem extends NavItem {
    /** Se assente, la voce è visibile a tutti i ruoli. */
    roles?: Role[];
}

const mainNavItems: RoleNavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Ore', url: '/time-entries', icon: Clock },
    { title: 'Utenti', url: '/users', icon: Users, roles: ['capo', 'capo_area', 'hr'] },
    { title: 'Aree', url: '/areas', icon: Layers, roles: ['capo'] },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const items = mainNavItems.filter((item) => !item.roles || item.roles.includes(auth.user.role));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

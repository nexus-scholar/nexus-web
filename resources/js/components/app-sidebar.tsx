import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, workspace } = usePage().props;
    const isOperator = Boolean(auth.user?.is_operator);

    const workspaceNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (workspace.current) {
        workspaceNavItems.push(
            {
                title: 'Workspace settings',
                href: workspace.current.settings_url,
                icon: Settings,
            },
            {
                title: 'Members',
                href: workspace.current.members_url,
                icon: Users,
            },
        );
    }

    const operatorNavItems: NavItem[] = [];

    if (isOperator) {
        operatorNavItems.push(
            {
                title: 'Operator users',
                href: '/operator/users',
                icon: ShieldCheck,
            },
            {
                title: 'Operator workspaces',
                href: '/operator/workspaces',
                icon: Building2,
            },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <WorkspaceSwitcher />
                <NavMain items={workspaceNavItems} label="Workspace" />
                {operatorNavItems.length > 0 && (
                    <NavMain items={operatorNavItems} label="Operations" />
                )}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/70">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

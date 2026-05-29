import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FolderGit2,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/nexus-scholar/nexus-web',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://github.com/nexus-scholar/core',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth, workspace } = usePage().props;
    const isOperator = Boolean(auth.user?.is_operator);

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (workspace.current) {
        mainNavItems.push(
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

    if (isOperator) {
        mainNavItems.push(
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
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

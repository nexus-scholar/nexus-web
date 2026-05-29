import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

export function NavUser() {
    const { auth } = usePage().props;
    const cleanup = useMobileNavigation();

    if (!auth.user) {
        return null;
    }

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <SidebarMenu className="gap-1">
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    size="lg"
                    tooltip={{ children: auth.user.name }}
                    className="h-11 text-sidebar-accent-foreground"
                    data-test="sidebar-menu-button"
                >
                    <Link href={edit()} prefetch>
                        <UserInfo user={auth.user} />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem className="grid grid-cols-2 gap-1 group-data-[collapsible=icon]:grid-cols-1">
                <SidebarMenuButton
                    asChild
                    size="sm"
                    tooltip={{ children: 'Account settings' }}
                >
                    <Link href={edit()} prefetch>
                        <Settings />
                        <span>Settings</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuButton
                    asChild
                    size="sm"
                    tooltip={{ children: 'Log out' }}
                >
                    <Link
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                    >
                        <LogOut />
                        <span>Log out</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

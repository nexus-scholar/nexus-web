import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Users } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';

export function WorkspaceSwitcher() {
    const { workspace } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    if (!workspace.current) {
        return null;
    }

    const switchWorkspace = (workspaceId: string) => {
        if (workspace.current?.id === workspaceId) {
            return;
        }

        router.post(
            '/workspaces/switch',
            { workspace_id: workspaceId },
            { preserveScroll: true },
        );
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                {workspace.current.type === 'shared' ? (
                                    <Users className="size-4" />
                                ) : (
                                    <Building2 className="size-4" />
                                )}
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {workspace.current.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {workspace.current.role ?? 'member'}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        }
                    >
                        <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {workspace.memberships.map((membership) => (
                            <DropdownMenuItem
                                key={membership.workspace.id}
                                onClick={() =>
                                    switchWorkspace(membership.workspace.id)
                                }
                                className="cursor-pointer gap-2"
                            >
                                <div className="flex size-4 items-center justify-center">
                                    {workspace.current?.id ===
                                        membership.workspace.id && (
                                        <Check className="size-4" />
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <div className="truncate font-medium">
                                        {membership.workspace.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {membership.role_label}
                                    </div>
                                </div>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

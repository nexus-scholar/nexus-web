import { router, usePage } from '@inertiajs/react';
import { Building2, Users } from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

export function WorkspaceSwitcher() {
    const { workspace } = usePage().props;

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
        <SidebarGroup className="px-2 py-1">
            <SidebarGroupLabel className="text-[0.7rem] font-medium">
                Workspaces
            </SidebarGroupLabel>
            <SidebarGroupContent>
                <SidebarMenu className="gap-0.5">
                    {workspace.memberships.map((membership) => {
                        const isActive =
                            workspace.current?.id === membership.workspace.id;
                        const Icon =
                            membership.workspace.type === 'shared'
                                ? Users
                                : Building2;

                        return (
                            <SidebarMenuItem key={membership.workspace.id}>
                                <SidebarMenuButton
                                    type="button"
                                    disabled={isActive}
                                    isActive={isActive}
                                    onClick={() =>
                                        switchWorkspace(membership.workspace.id)
                                    }
                                    tooltip={{
                                        children: `${membership.workspace.name} (${membership.role_label})`,
                                    }}
                                    className={cn(
                                        'h-11 disabled:pointer-events-none disabled:opacity-100',
                                        'data-[active=true]:bg-brand-muted data-[active=true]:text-brand-muted-foreground',
                                    )}
                                >
                                    <Icon />
                                    <div className="flex min-w-0 flex-1 flex-col overflow-hidden text-left leading-tight group-data-[collapsible=icon]:hidden">
                                        <span className="block truncate font-medium">
                                            {membership.workspace.name}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {membership.role_label}
                                        </span>
                                    </div>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceStatusBadge } from '@/components/workspace-status-badge';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { workspace } = usePage().props;
    const createWorkspace = useForm({ name: '' });

    const submitWorkspace = (event: FormEvent) => {
        event.preventDefault();

        createWorkspace.post('/workspaces', {
            preserveScroll: true,
            onSuccess: () => createWorkspace.reset(),
        });
    };

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
                    <section className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {workspace.current?.name ?? 'Workspace'}
                                </CardTitle>
                                <CardDescription>
                                    Your active workspace and collaboration
                                    context.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Type
                                    </div>
                                    <WorkspaceStatusBadge
                                        status={
                                            workspace.current?.suspended_at
                                                ? 'disabled'
                                                : (workspace.current?.type ??
                                                  'disabled')
                                        }
                                    >
                                        {workspace.current?.type ?? 'None'}
                                    </WorkspaceStatusBadge>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Role
                                    </div>
                                    <Badge variant="outline">
                                        {workspace.current?.role ?? 'member'}
                                    </Badge>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Accessible workspaces
                                    </div>
                                    <div className="text-lg font-semibold">
                                        {workspace.memberships.length}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Workspace access</CardTitle>
                                <CardDescription>
                                    Switch between your personal and shared
                                    workspaces from the sidebar.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                {workspace.memberships.map((membership) => (
                                    <div
                                        key={membership.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="truncate font-medium">
                                                {membership.workspace.name}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {membership.role_label}
                                            </div>
                                        </div>
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={
                                                    membership.workspace
                                                        .settings_url
                                                }
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </section>

                    <aside>
                        <Card>
                            <CardHeader>
                                <CardTitle>Create shared workspace</CardTitle>
                                <CardDescription>
                                    Use a shared workspace for a lab, research
                                    group, or university team.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={submitWorkspace}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="workspace-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="workspace-name"
                                            value={createWorkspace.data.name}
                                            onChange={(event) =>
                                                createWorkspace.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Evidence Synthesis Lab"
                                        />
                                        <InputError
                                            message={
                                                createWorkspace.errors.name
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={createWorkspace.processing}
                                    >
                                        Create workspace
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

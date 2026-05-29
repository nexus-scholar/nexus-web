import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FileText, FolderPlus, ShieldCheck, UsersRound } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { MetricCard } from '@/components/metric-card';
import { PageHeader, PageShell } from '@/components/page-shell';
import {
    ProjectStatusBadge,
    ProtocolStatusBadge,
} from '@/components/project-status-badge';
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
import type { ProjectStatus, ProtocolStatus, ReviewType } from '@/types';

type DashboardProject = {
    id: string;
    name: string;
    slug: string;
    review_type: ReviewType;
    review_type_label: string;
    status: ProjectStatus;
    status_label: string;
    protocol_status: ProtocolStatus | null;
    protocol_status_label: string | null;
    members_count: number;
    overview_url: string;
    protocol_url: string;
    search_plan_url: string;
};

type DashboardProps = {
    projects: DashboardProject[];
    can: {
        create_project: boolean;
    };
};

export default function Dashboard() {
    const { can, projects, workspace } = usePage<DashboardProps>().props;
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

            <PageShell>
                <PageHeader
                    eyebrow="Workspace"
                    title={workspace.current?.name ?? 'Dashboard'}
                    description="Workspace access, membership context, and the next shared lab setup."
                    actions={
                        <>
                            {can.create_project && (
                                <Button size="sm" asChild>
                                    <Link href="/projects/create">
                                        New project
                                    </Link>
                                </Button>
                            )}
                            {workspace.current && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={workspace.current.settings_url}>
                                        Workspace settings
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Current role"
                        value={workspace.current?.role ?? 'member'}
                        description="Permission scope for the active workspace."
                        icon={ShieldCheck}
                    />
                    <MetricCard
                        label="Active projects"
                        value={projects.length}
                        description="Projects visible in the current workspace."
                        icon={FileText}
                    />
                    <MetricCard
                        label="Workspace type"
                        value={workspace.current?.type ?? 'none'}
                        description={
                            workspace.current?.suspended_at
                                ? 'Suspended by an operator.'
                                : 'Ready for review work.'
                        }
                        icon={UsersRound}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <section className="space-y-4">
                        <Card>
                            <CardHeader className="flex-row items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <CardTitle>Projects</CardTitle>
                                    <CardDescription>
                                        Protocol-backed reviews in this
                                        workspace.
                                    </CardDescription>
                                </div>
                                {can.create_project && (
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href="/projects/create">
                                            Create
                                        </Link>
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent className="divide-y">
                                {projects.length === 0 ? (
                                    <div className="grid gap-3 py-4 text-sm text-muted-foreground">
                                        <div className="flex items-center gap-2">
                                            <FolderPlus className="size-4" />
                                            <span>
                                                No projects have been created in
                                                this workspace yet.
                                            </span>
                                        </div>
                                        {can.create_project && (
                                            <Button
                                                size="sm"
                                                className="w-fit"
                                                asChild
                                            >
                                                <Link href="/projects/create">
                                                    Create project
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                ) : (
                                    projects.map((project) => (
                                        <div
                                            key={project.id}
                                            className="grid gap-3 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                        >
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-medium">
                                                    {project.name}
                                                </div>
                                                <div className="mt-1 flex flex-wrap gap-2">
                                                    <ProjectStatusBadge
                                                        status={project.status}
                                                    />
                                                    {project.protocol_status && (
                                                        <ProtocolStatusBadge
                                                            status={
                                                                project.protocol_status
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {project.status !== 'draft' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                project.search_plan_url
                                                            }
                                                        >
                                                            Search plan
                                                        </Link>
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            project.protocol_url
                                                        }
                                                    >
                                                        Protocol
                                                    </Link>
                                                </Button>
                                                <Button size="sm" asChild>
                                                    <Link
                                                        href={
                                                            project.overview_url
                                                        }
                                                    >
                                                        Open
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <CardTitle>Active workspace</CardTitle>
                                    <CardDescription>
                                        Collaboration context for this session.
                                    </CardDescription>
                                </div>
                                <WorkspaceStatusBadge
                                    status={
                                        workspace.current?.suspended_at
                                            ? 'disabled'
                                            : (workspace.current?.type ??
                                              'disabled')
                                    }
                                >
                                    {workspace.current?.suspended_at
                                        ? 'Suspended'
                                        : (workspace.current?.type ?? 'None')}
                                </WorkspaceStatusBadge>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-md border bg-muted/30 p-3">
                                    <div className="text-xs text-muted-foreground">
                                        Name
                                    </div>
                                    <div className="mt-1 truncate text-sm font-medium">
                                        {workspace.current?.name ?? 'None'}
                                    </div>
                                </div>
                                <div className="rounded-md border bg-muted/30 p-3">
                                    <div className="text-xs text-muted-foreground">
                                        Role
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="mt-1 capitalize"
                                    >
                                        {workspace.current?.role ?? 'member'}
                                    </Badge>
                                </div>
                                <div className="rounded-md border bg-muted/30 p-3">
                                    <div className="text-xs text-muted-foreground">
                                        Status
                                    </div>
                                    <div className="mt-1 text-sm font-medium">
                                        {workspace.current?.suspended_at
                                            ? 'Operator suspended'
                                            : 'Active'}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Workspace access</CardTitle>
                                <CardDescription>
                                    Personal and shared workspaces available in
                                    the sidebar.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                {workspace.memberships.map((membership) => (
                                    <div
                                        key={membership.id}
                                        className="grid gap-3 py-3 sm:grid-cols-[1fr_auto] sm:items-center"
                                    >
                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-medium">
                                                {membership.workspace.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {membership.role_label}
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
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
                                    Start a lab or research group workspace.
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
                                        className="w-full"
                                    >
                                        Create workspace
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </PageShell>
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

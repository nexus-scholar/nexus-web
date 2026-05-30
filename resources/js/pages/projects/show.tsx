import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ClipboardList,
    FileText,
    GitMerge,
    LockKeyhole,
    LibraryBig,
    SearchCheck,
    UsersRound,
} from 'lucide-react';
import { MetricCard } from '@/components/metric-card';
import { PageHeader, PageShell } from '@/components/page-shell';
import {
    ProjectRoleBadge,
    ProjectStatusBadge,
    ProtocolStatusBadge,
} from '@/components/project-status-badge';
import { ProtocolReadinessList } from '@/components/protocol-readiness-list';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { WorkflowStepCard } from '@/components/workflow-step-card';
import type {
    ProjectRole,
    ProjectStatus,
    ProtocolReadinessItem,
    ProtocolStatus,
    ReviewType,
} from '@/types';

type ProjectPayload = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    review_type: ReviewType;
    review_type_label: string;
    status: ProjectStatus;
    status_label: string;
    locked_at: string | null;
    role: ProjectRole | null;
    role_label: string | null;
    workspace: {
        id: string;
        name: string;
        type: 'personal' | 'shared';
    };
    urls: {
        overview: string;
        protocol: string;
        search_plan: string;
        activity: string;
        corpus: string;
        deduplication: string;
        screening: string;
    };
    corpus: {
        available: boolean;
        source: 'draft' | 'locked';
        unique_works: number;
        raw_query_links: number;
    };
    protocol: {
        id: string;
        status: ProtocolStatus;
        status_label: string;
        version: number;
        completed_at: string | null;
    } | null;
};

type ProjectMember = {
    id: number;
    role: ProjectRole;
    role_label: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
};

type Props = {
    project: ProjectPayload;
    protocolReadiness: ProtocolReadinessItem[];
    members: ProjectMember[];
    can: {
        update_protocol: boolean;
        complete_protocol: boolean;
        view_search_plan: boolean;
        update_search_plan: boolean;
        run_search: boolean;
        view_corpus: boolean;
        view_deduplication: boolean;
        view_screening: boolean;
        view_activity: boolean;
    };
};

export default function ProjectOverview({
    can,
    members,
    project,
    protocolReadiness,
}: Props) {
    const requiredItems = protocolReadiness.filter(
        (item) => item.required !== false,
    );
    const completedRequiredItems = requiredItems.filter(
        (item) => item.complete,
    );
    const protocolReady =
        requiredItems.length > 0 &&
        requiredItems.length === completedRequiredItems.length;

    return (
        <>
            <Head title={project.name} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title={project.name}
                    description="Project setup, protocol readiness, and role-scoped access."
                    actions={
                        <>
                            {can.update_protocol && (
                                <Button size="sm" asChild>
                                    <Link href={project.urls.protocol}>
                                        Edit protocol
                                    </Link>
                                </Button>
                            )}
                            {can.view_search_plan && protocolReady && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={project.urls.search_plan}>
                                        Search plan
                                    </Link>
                                </Button>
                            )}
                            {can.view_corpus && project.corpus.available && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={project.urls.corpus}>
                                        Corpus
                                    </Link>
                                </Button>
                            )}
                            {can.view_deduplication &&
                                project.corpus.available && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={project.urls.deduplication}>
                                            Deduplication
                                        </Link>
                                    </Button>
                                )}
                            {can.view_screening && project.locked_at && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={project.urls.screening}>
                                        Screening
                                    </Link>
                                </Button>
                            )}
                            {can.view_activity && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={project.urls.activity}>
                                        Activity
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 md:grid-cols-4">
                    <MetricCard
                        label="Project status"
                        value={project.status_label}
                        description="Workflow stage for this review."
                        icon={Activity}
                    />
                    <MetricCard
                        label="Protocol version"
                        value={project.protocol?.version ?? 0}
                        description={project.protocol?.status_label ?? 'Draft'}
                        icon={FileText}
                    />
                    <MetricCard
                        label="Search readiness"
                        value={protocolReady ? 'Ready' : 'Blocked'}
                        description="Protocol gate for search planning."
                        icon={SearchCheck}
                    />
                    <MetricCard
                        label="Members"
                        value={members.length}
                        description="Explicit project memberships."
                        icon={UsersRound}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <section className="space-y-4">
                        <Card>
                            <CardHeader className="flex-row items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <CardTitle>Project state</CardTitle>
                                    <CardDescription>
                                        Search stays unavailable until protocol
                                        readiness is complete.
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap justify-end gap-2">
                                    <ProjectStatusBadge
                                        status={project.status}
                                    />
                                    {project.protocol && (
                                        <ProtocolStatusBadge
                                            status={project.protocol.status}
                                        />
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-md border bg-muted/30 p-3">
                                    <div className="text-xs text-muted-foreground">
                                        Workspace
                                    </div>
                                    <div className="mt-1 truncate text-sm font-medium">
                                        {project.workspace.name}
                                    </div>
                                </div>
                                <div className="rounded-md border bg-muted/30 p-3">
                                    <div className="text-xs text-muted-foreground">
                                        Your role
                                    </div>
                                    <div className="mt-1">
                                        {project.role ? (
                                            <ProjectRoleBadge
                                                status={project.role}
                                            />
                                        ) : (
                                            <span className="text-sm font-medium">
                                                Workspace admin
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="rounded-md border bg-muted/30 p-3">
                                    <div className="text-xs text-muted-foreground">
                                        Lock state
                                    </div>
                                    <div className="mt-1 flex items-center gap-2 text-sm font-medium">
                                        <LockKeyhole className="size-4 text-muted-foreground" />
                                        {project.locked_at
                                            ? 'Corpus locked'
                                            : 'Unlocked'}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <ProtocolReadinessList items={protocolReadiness} />
                    </section>

                    <aside className="space-y-3">
                        <WorkflowStepCard
                            step={1}
                            title="Project shell"
                            description="Workspace, owner, review type, and protocol record exist."
                            status="complete"
                        />
                        <WorkflowStepCard
                            step={2}
                            title="Protocol readiness"
                            description="Complete required protocol fields before search."
                            status={protocolReady ? 'complete' : 'current'}
                            action={
                                can.update_protocol && (
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={project.urls.protocol}>
                                            Open protocol
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                        <WorkflowStepCard
                            step={3}
                            title="Search plan"
                            description="Draft provider-ready queries for the search run."
                            status={
                                project.corpus.available
                                    ? 'complete'
                                    : protocolReady
                                      ? 'current'
                                      : 'pending'
                            }
                            action={
                                can.view_search_plan &&
                                protocolReady && (
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={project.urls.search_plan}>
                                            Open search plan
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                        <WorkflowStepCard
                            step={4}
                            title="Corpus review"
                            description="Inspect works, metadata gaps, and provider provenance."
                            status={
                                project.locked_at
                                    ? 'complete'
                                    : project.corpus.available
                                      ? 'complete'
                                      : 'pending'
                            }
                            action={
                                can.view_corpus &&
                                project.corpus.available && (
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={project.urls.corpus}>
                                            <LibraryBig className="size-4" />
                                            Open corpus
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                        <WorkflowStepCard
                            step={5}
                            title="Deduplicate and lock"
                            description="Review duplicate clusters and create the representative corpus snapshot."
                            status={
                                project.locked_at
                                    ? 'complete'
                                    : project.corpus.available
                                      ? 'current'
                                      : 'pending'
                            }
                            action={
                                can.view_deduplication &&
                                project.corpus.available && (
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={project.urls.deduplication}>
                                            <GitMerge className="size-4" />
                                            Open deduplication
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                        <WorkflowStepCard
                            step={6}
                            title="Title and abstract screening"
                            description="Assign locked records and record reviewer decisions."
                            status={
                                project.status === 'screening' ||
                                project.status === 'adjudication'
                                    ? 'current'
                                    : project.locked_at
                                      ? 'current'
                                      : 'pending'
                            }
                            action={
                                can.view_screening &&
                                project.locked_at && (
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={project.urls.screening}>
                                            <ClipboardList className="size-4" />
                                            Open screening
                                        </Link>
                                    </Button>
                                )
                            }
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Project members</CardTitle>
                                <CardDescription>
                                    Explicit project roles.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                {members.map((member) => (
                                    <div
                                        key={member.id}
                                        className="py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="truncate text-sm font-medium">
                                            {member.user.name}
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-2">
                                            <ProjectRoleBadge
                                                status={member.role}
                                            />
                                            <span className="truncate text-xs text-muted-foreground">
                                                {member.user.email}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </PageShell>
        </>
    );
}

ProjectOverview.layout = {
    breadcrumbs: [
        {
            title: 'Project',
            href: '#',
        },
    ],
};

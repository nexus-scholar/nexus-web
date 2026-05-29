import { Head, useForm } from '@inertiajs/react';
import { Building2, CirclePause, UsersRound } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { MetricCard } from '@/components/metric-card';
import { PageHeader, PageShell } from '@/components/page-shell';
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

type OperatorWorkspace = {
    id: string;
    name: string;
    slug: string;
    type: 'personal' | 'shared';
    owner: {
        id: number;
        name: string;
        email: string;
    };
    active_memberships_count: number;
    suspended_at: string | null;
    suspended_reason: string | null;
    created_at: string;
};

type Props = {
    workspaces: OperatorWorkspace[];
};

function WorkspaceStatusForm({ workspace }: { workspace: OperatorWorkspace }) {
    const form = useForm({
        status: workspace.suspended_at ? 'active' : 'suspended',
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.patch(`/operator/workspaces/${workspace.id}/status`, {
            preserveScroll: true,
            onSuccess: () => form.reset('reason'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-2 sm:grid-cols-[16rem_auto]"
        >
            <div>
                <Input
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    placeholder="Audit reason"
                />
                <InputError message={form.errors.reason} className="mt-2" />
            </div>
            <Button
                type="submit"
                variant={workspace.suspended_at ? 'outline' : 'default'}
                disabled={form.processing}
            >
                {workspace.suspended_at ? 'Unsuspend' : 'Suspend'}
            </Button>
        </form>
    );
}

export default function OperatorWorkspaces({ workspaces }: Props) {
    const suspendedWorkspaces = workspaces.filter(
        (workspace) => workspace.suspended_at,
    ).length;
    const activeMemberships = workspaces.reduce(
        (total, workspace) => total + workspace.active_memberships_count,
        0,
    );

    return (
        <>
            <Head title="Operator workspaces" />

            <PageShell>
                <PageHeader
                    eyebrow="Operations"
                    title="Workspace controls"
                    description="Workspace status review with audit reasons for suspend and restore actions."
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Workspaces"
                        value={workspaces.length}
                        description="Personal and shared workspaces."
                        icon={Building2}
                    />
                    <MetricCard
                        label="Suspended"
                        value={suspendedWorkspaces}
                        description="Workspaces blocked by operators."
                        icon={CirclePause}
                    />
                    <MetricCard
                        label="Active memberships"
                        value={activeMemberships}
                        description="Current active workspace seats."
                        icon={UsersRound}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Platform workspaces</CardTitle>
                        <CardDescription>
                            Suspend or restore workspace access with a recorded
                            reason.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {workspaces.map((workspace) => (
                            <div
                                key={workspace.id}
                                className="grid gap-3 py-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-medium">
                                            {workspace.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {workspace.type}
                                        </Badge>
                                        {workspace.suspended_at && (
                                            <Badge variant="destructive">
                                                suspended
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Owner: {workspace.owner.name} -{' '}
                                        {workspace.active_memberships_count}{' '}
                                        active members
                                    </div>
                                    {workspace.suspended_reason && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {workspace.suspended_reason}
                                        </div>
                                    )}
                                </div>
                                <WorkspaceStatusForm workspace={workspace} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

OperatorWorkspaces.layout = {
    breadcrumbs: [
        {
            title: 'Operator workspaces',
            href: '/operator/workspaces',
        },
    ],
};

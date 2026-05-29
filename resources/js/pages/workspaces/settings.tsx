import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, ShieldCheck, UsersRound } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { MetricCard } from '@/components/metric-card';
import { PageHeader, PageShell } from '@/components/page-shell';
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
import type { WorkspaceSummary } from '@/types';

type Props = {
    selectedWorkspace: WorkspaceSummary;
    can: {
        update: boolean;
        manage_members: boolean;
    };
};

export default function WorkspaceSettings({ can, selectedWorkspace }: Props) {
    const form = useForm({
        name: selectedWorkspace.name,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.patch(`/workspaces/${selectedWorkspace.id}/settings`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`${selectedWorkspace.name} settings`} />

            <PageShell>
                <PageHeader
                    eyebrow="Workspace settings"
                    title={selectedWorkspace.name}
                    description="Workspace identity, role context, and member entry points."
                    actions={
                        can.manage_members && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={`/workspaces/${selectedWorkspace.id}/members`}
                                >
                                    Manage members
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Type"
                        value={selectedWorkspace.type}
                        description="Workspace ownership model."
                        icon={Building2}
                    />
                    <MetricCard
                        label="Role"
                        value={selectedWorkspace.role ?? 'member'}
                        description="Your permission level here."
                        icon={ShieldCheck}
                    />
                    <MetricCard
                        label="Status"
                        value={
                            selectedWorkspace.suspended_at
                                ? 'suspended'
                                : 'active'
                        }
                        description="Operator-controlled access state."
                        icon={UsersRound}
                    />
                </div>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="space-y-1">
                            <CardTitle>Workspace profile</CardTitle>
                            <CardDescription>
                                Owners can rename the workspace.
                            </CardDescription>
                        </div>
                        <WorkspaceStatusBadge
                            status={
                                selectedWorkspace.suspended_at
                                    ? 'disabled'
                                    : selectedWorkspace.type
                            }
                        >
                            {selectedWorkspace.suspended_at
                                ? 'Suspended'
                                : selectedWorkspace.type}
                        </WorkspaceStatusBadge>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="max-w-xl space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="workspace-name">Name</Label>
                                <Input
                                    id="workspace-name"
                                    value={form.data.name}
                                    disabled={!can.update}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    disabled={!can.update || form.processing}
                                    size="sm"
                                >
                                    Save
                                </Button>
                                {can.manage_members && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={`/workspaces/${selectedWorkspace.id}/members`}
                                        >
                                            Manage members
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

WorkspaceSettings.layout = {
    breadcrumbs: [
        {
            title: 'Workspace settings',
            href: '#',
        },
    ],
};

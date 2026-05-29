import { Head, useForm } from '@inertiajs/react';
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
        <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
            <Input
                value={form.data.reason}
                onChange={(event) => form.setData('reason', event.target.value)}
                placeholder="Audit reason"
                className="sm:w-64"
            />
            <Button
                type="submit"
                variant={workspace.suspended_at ? 'outline' : 'default'}
            >
                {workspace.suspended_at ? 'Unsuspend' : 'Suspend'}
            </Button>
            <InputError message={form.errors.reason} />
        </form>
    );
}

export default function OperatorWorkspaces({ workspaces }: Props) {
    return (
        <>
            <Head title="Operator workspaces" />

            <div className="space-y-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Workspaces</CardTitle>
                        <CardDescription>
                            Operator view for workspace status controls.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {workspaces.map((workspace) => (
                            <div
                                key={workspace.id}
                                className="grid gap-3 py-4 lg:grid-cols-[1fr_auto]"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
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
                                    <div className="text-sm text-muted-foreground">
                                        Owner: {workspace.owner.name} -{' '}
                                        {workspace.active_memberships_count}{' '}
                                        active members
                                    </div>
                                    {workspace.suspended_reason && (
                                        <div className="mt-1 text-sm text-muted-foreground">
                                            {workspace.suspended_reason}
                                        </div>
                                    )}
                                </div>
                                <WorkspaceStatusForm workspace={workspace} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
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

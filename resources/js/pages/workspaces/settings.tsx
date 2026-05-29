import { Head, Link, useForm } from '@inertiajs/react';
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

            <div className="space-y-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{selectedWorkspace.name}</CardTitle>
                        <CardDescription>
                            Workspace identity and access controls.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <div className="text-sm text-muted-foreground">
                                Type
                            </div>
                            <Badge variant="secondary">
                                {selectedWorkspace.type}
                            </Badge>
                        </div>
                        <div>
                            <div className="text-sm text-muted-foreground">
                                Role
                            </div>
                            <Badge variant="outline">
                                {selectedWorkspace.role ?? 'member'}
                            </Badge>
                        </div>
                        <div>
                            <div className="text-sm text-muted-foreground">
                                Status
                            </div>
                            <Badge
                                variant={
                                    selectedWorkspace.suspended_at
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {selectedWorkspace.suspended_at
                                    ? 'suspended'
                                    : 'active'}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Settings</CardTitle>
                        <CardDescription>
                            Owners can rename the workspace.
                        </CardDescription>
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
                                >
                                    Save
                                </Button>
                                {can.manage_members && (
                                    <Button variant="outline" asChild>
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
            </div>
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

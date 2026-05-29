import { Head, router, useForm } from '@inertiajs/react';
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

type WorkspaceMember = {
    id: number;
    role: 'owner' | 'admin' | 'member';
    role_label: string;
    joined_at: string | null;
    can_remove: boolean;
    can_update_role: boolean;
    user: {
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
    };
};

type WorkspaceInvitation = {
    id: string;
    email: string;
    role: 'admin' | 'member';
    role_label: string;
    expires_at: string;
};

type Props = {
    selectedWorkspace: WorkspaceSummary;
    members: WorkspaceMember[];
    invitations: WorkspaceInvitation[];
    available_roles: Array<'admin' | 'member'>;
    can: {
        manage_members: boolean;
    };
};

function InviteMemberForm({
    availableRoles,
    selectedWorkspace,
}: {
    availableRoles: Array<'admin' | 'member'>;
    selectedWorkspace: WorkspaceSummary;
}) {
    const form = useForm({
        email: '',
        role: availableRoles.includes('admin') ? 'admin' : 'member',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(`/workspaces/${selectedWorkspace.id}/invitations`, {
            preserveScroll: true,
            onSuccess: () => form.reset('email'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 md:grid-cols-[1fr_160px_auto]"
        >
            <div className="grid gap-2">
                <Label htmlFor="invite-email">Email</Label>
                <Input
                    id="invite-email"
                    type="email"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    placeholder="reviewer@example.edu"
                />
                <InputError message={form.errors.email} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="invite-role">Role</Label>
                <select
                    id="invite-role"
                    value={form.data.role}
                    onChange={(event) =>
                        form.setData(
                            'role',
                            event.target.value as 'admin' | 'member',
                        )
                    }
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    {availableRoles.map((role) => (
                        <option key={role} value={role}>
                            {role}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.role} />
            </div>
            <div className="flex items-end">
                <Button type="submit" disabled={form.processing}>
                    Invite
                </Button>
            </div>
        </form>
    );
}

export default function WorkspaceMembers({
    available_roles,
    can,
    invitations,
    members,
    selectedWorkspace,
}: Props) {
    const removeMember = (userId: number) => {
        router.delete(`/workspaces/${selectedWorkspace.id}/members/${userId}`, {
            preserveScroll: true,
        });
    };

    const updateRole = (userId: number, role: 'admin' | 'member') => {
        router.patch(
            `/workspaces/${selectedWorkspace.id}/members/${userId}/role`,
            { role },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`${selectedWorkspace.name} members`} />

            <div className="space-y-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{selectedWorkspace.name}</CardTitle>
                        <CardDescription>
                            Manage workspace membership and pending invitations.
                        </CardDescription>
                    </CardHeader>
                    {can.manage_members &&
                        selectedWorkspace.type === 'shared' && (
                            <CardContent>
                                <InviteMemberForm
                                    selectedWorkspace={selectedWorkspace}
                                    availableRoles={available_roles}
                                />
                            </CardContent>
                        )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Members</CardTitle>
                        <CardDescription>
                            Active users with access to this workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                className="grid gap-3 py-4 lg:grid-cols-[1fr_auto]"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {member.user.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {member.role_label}
                                        </Badge>
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {member.user.email}
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {member.can_update_role && (
                                        <select
                                            value={member.role}
                                            onChange={(event) =>
                                                updateRole(
                                                    member.user.id,
                                                    event.target.value as
                                                        | 'admin'
                                                        | 'member',
                                                )
                                            }
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="admin">admin</option>
                                            <option value="member">
                                                member
                                            </option>
                                        </select>
                                    )}
                                    {member.can_remove && (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                removeMember(member.user.id)
                                            }
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {invitations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending invitations</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {invitations.map((invitation) => (
                                <div
                                    key={invitation.id}
                                    className="flex items-center justify-between gap-4 py-3"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {invitation.email}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {invitation.role_label}
                                        </div>
                                    </div>
                                    <Badge variant="outline">pending</Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

WorkspaceMembers.layout = {
    breadcrumbs: [
        {
            title: 'Workspace members',
            href: '#',
        },
    ],
};

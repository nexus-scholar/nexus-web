import { Head, router, useForm } from '@inertiajs/react';
import { MailPlus, ShieldCheck, UsersRound } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
                <Select
                    value={form.data.role}
                    onValueChange={(role: 'admin' | 'member') =>
                        form.setData('role', role)
                    }
                >
                    <SelectTrigger id="invite-role" className="w-full">
                        <SelectValue placeholder="Select role" />
                    </SelectTrigger>
                    <SelectContent>
                        {availableRoles.map((role) => (
                            <SelectItem key={role} value={role}>
                                {role}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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

function MemberRoleSelect({
    member,
    onUpdateRole,
}: {
    member: WorkspaceMember;
    onUpdateRole: (userId: number, role: 'admin' | 'member') => void;
}) {
    return (
        <Select
            value={member.role}
            onValueChange={(role: 'admin' | 'member') =>
                onUpdateRole(member.user.id, role)
            }
        >
            <SelectTrigger className="w-32">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="admin">admin</SelectItem>
                <SelectItem value="member">member</SelectItem>
            </SelectContent>
        </Select>
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

            <PageShell>
                <PageHeader
                    eyebrow="Workspace access"
                    title={`${selectedWorkspace.name} members`}
                    description="Active members, roles, and pending invitations for this workspace."
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Members"
                        value={members.length}
                        description="Active users with workspace access."
                        icon={UsersRound}
                    />
                    <MetricCard
                        label="Pending invitations"
                        value={invitations.length}
                        description="Invites waiting for acceptance."
                        icon={MailPlus}
                    />
                    <MetricCard
                        label="Management"
                        value={can.manage_members ? 'enabled' : 'locked'}
                        description="Role controls for the active user."
                        icon={ShieldCheck}
                    />
                </div>

                {can.manage_members && selectedWorkspace.type === 'shared' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Invite member</CardTitle>
                            <CardDescription>
                                Add a reviewer or workspace administrator.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <InviteMemberForm
                                selectedWorkspace={selectedWorkspace}
                                availableRoles={available_roles}
                            />
                        </CardContent>
                    </Card>
                )}

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
                                className="grid gap-3 py-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-medium">
                                            {member.user.name}
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="capitalize"
                                        >
                                            {member.role_label}
                                        </Badge>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {member.user.email}
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {member.can_update_role && (
                                        <MemberRoleSelect
                                            member={member}
                                            onUpdateRole={updateRole}
                                        />
                                    )}
                                    {member.can_remove && (
                                        <Button
                                            variant="outline"
                                            size="sm"
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
                            <CardDescription>
                                Invites already issued for this workspace.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {invitations.map((invitation) => (
                                <div
                                    key={invitation.id}
                                    className="grid gap-3 py-3 sm:grid-cols-[1fr_auto] sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-medium">
                                            {invitation.email}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {invitation.role_label}
                                        </div>
                                    </div>
                                    <Badge variant="outline">pending</Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </PageShell>
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

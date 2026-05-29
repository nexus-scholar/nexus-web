import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck, UserRoundX, UsersRound } from 'lucide-react';
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

type OperatorUser = {
    id: number;
    name: string;
    email: string;
    is_operator: boolean;
    disabled_at: string | null;
    disabled_reason: string | null;
    created_at: string;
};

type Props = {
    users: OperatorUser[];
};

function UserStatusForm({ user }: { user: OperatorUser }) {
    const form = useForm({
        status: user.disabled_at ? 'active' : 'disabled',
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.patch(`/operator/users/${user.id}/status`, {
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
                variant={user.disabled_at ? 'outline' : 'default'}
                disabled={form.processing}
            >
                {user.disabled_at ? 'Enable' : 'Disable'}
            </Button>
        </form>
    );
}

export default function OperatorUsers({ users }: Props) {
    const disabledUsers = users.filter((user) => user.disabled_at).length;
    const operators = users.filter((user) => user.is_operator).length;

    return (
        <>
            <Head title="Operator users" />

            <PageShell>
                <PageHeader
                    eyebrow="Operations"
                    title="User controls"
                    description="Account status controls with audit reasons for every operator action."
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Users"
                        value={users.length}
                        description="Accounts visible to operators."
                        icon={UsersRound}
                    />
                    <MetricCard
                        label="Disabled"
                        value={disabledUsers}
                        description="Accounts blocked from app access."
                        icon={UserRoundX}
                    />
                    <MetricCard
                        label="Operators"
                        value={operators}
                        description="Accounts with platform controls."
                        icon={ShieldCheck}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Platform users</CardTitle>
                        <CardDescription>
                            Disable or restore access with a recorded reason.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {users.map((user) => (
                            <div
                                key={user.id}
                                className="grid gap-3 py-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-medium">
                                            {user.name}
                                        </span>
                                        {user.is_operator && (
                                            <Badge variant="secondary">
                                                operator
                                            </Badge>
                                        )}
                                        {user.disabled_at && (
                                            <Badge variant="destructive">
                                                disabled
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {user.email}
                                    </div>
                                    {user.disabled_reason && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {user.disabled_reason}
                                        </div>
                                    )}
                                </div>
                                <UserStatusForm user={user} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

OperatorUsers.layout = {
    breadcrumbs: [
        {
            title: 'Operator users',
            href: '/operator/users',
        },
    ],
};

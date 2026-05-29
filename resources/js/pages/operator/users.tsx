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
        <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
            <Input
                value={form.data.reason}
                onChange={(event) => form.setData('reason', event.target.value)}
                placeholder="Audit reason"
                className="sm:w-64"
            />
            <Button
                type="submit"
                variant={user.disabled_at ? 'outline' : 'default'}
            >
                {user.disabled_at ? 'Enable' : 'Disable'}
            </Button>
            <InputError message={form.errors.reason} />
        </form>
    );
}

export default function OperatorUsers({ users }: Props) {
    return (
        <>
            <Head title="Operator users" />

            <div className="space-y-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Users</CardTitle>
                        <CardDescription>
                            Operator view for account status controls.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {users.map((user) => (
                            <div
                                key={user.id}
                                className="grid gap-3 py-4 lg:grid-cols-[1fr_auto]"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
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
                                    <div className="text-sm text-muted-foreground">
                                        {user.email}
                                    </div>
                                    {user.disabled_reason && (
                                        <div className="mt-1 text-sm text-muted-foreground">
                                            {user.disabled_reason}
                                        </div>
                                    )}
                                </div>
                                <UserStatusForm user={user} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
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

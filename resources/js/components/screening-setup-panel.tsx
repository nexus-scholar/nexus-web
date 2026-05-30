import { useForm } from '@inertiajs/react';
import { PlayCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ScreeningReviewer, ScreeningSnapshot } from '@/types';

type ScreeningSetupForm = {
    name: string;
    required_reviewer_count: number;
    reviewer_ids: number[];
};

type ScreeningSetupPanelProps = {
    actionUrl: string;
    availableReviewers: ScreeningReviewer[];
    defaultRequiredReviewerCount: number;
    disabled?: boolean;
    snapshot: ScreeningSnapshot | null;
};

export function ScreeningSetupPanel({
    actionUrl,
    availableReviewers,
    defaultRequiredReviewerCount,
    disabled = false,
    snapshot,
}: ScreeningSetupPanelProps) {
    const form = useForm<ScreeningSetupForm>({
        name: 'Title and abstract screening',
        required_reviewer_count: defaultRequiredReviewerCount,
        reviewer_ids: availableReviewers.map((reviewer) => reviewer.id),
    });
    const blocked =
        disabled ||
        !snapshot ||
        !snapshot.representative_snapshot ||
        snapshot.work_count === 0 ||
        availableReviewers.length === 0;

    const toggleReviewer = (id: number, checked: boolean) => {
        form.setData(
            'reviewer_ids',
            checked
                ? [...form.data.reviewer_ids, id]
                : form.data.reviewer_ids.filter(
                      (reviewerId) => reviewerId !== id,
                  ),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(actionUrl, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Start title and abstract screening</CardTitle>
                <CardDescription>
                    Assign the locked representative snapshot to project
                    reviewers.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="space-y-5" onSubmit={submit}>
                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem]">
                        <div className="space-y-2">
                            <Label htmlFor="screening-name">Batch name</Label>
                            <Input
                                id="screening-name"
                                value={form.data.name}
                                onChange={(event) => {
                                    form.setData('name', event.target.value);
                                }}
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="reviewer-count">
                                Required reviewers
                            </Label>
                            <Input
                                id="reviewer-count"
                                min={1}
                                max={6}
                                type="number"
                                value={form.data.required_reviewer_count}
                                onChange={(event) => {
                                    form.setData(
                                        'required_reviewer_count',
                                        Number(event.target.value),
                                    );
                                }}
                            />
                            <InputError
                                message={form.errors.required_reviewer_count}
                            />
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label>Reviewers</Label>
                            <ScreeningStatusBadge
                                status={
                                    form.data.reviewer_ids.length >=
                                    form.data.required_reviewer_count
                                        ? 'active'
                                        : 'pending'
                                }
                                label={`${form.data.reviewer_ids.length} selected`}
                            />
                        </div>
                        <div className="grid gap-2 md:grid-cols-2">
                            {availableReviewers.map((reviewer) => (
                                <label
                                    key={reviewer.id}
                                    className="flex items-start gap-3 rounded-md border bg-card p-3 text-sm shadow-xs"
                                >
                                    <Checkbox
                                        checked={form.data.reviewer_ids.includes(
                                            reviewer.id,
                                        )}
                                        onCheckedChange={(checked) => {
                                            toggleReviewer(
                                                reviewer.id,
                                                checked === true,
                                            );
                                        }}
                                    />
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">
                                            {reviewer.name}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {reviewer.email} /{' '}
                                            {reviewer.role_label}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.reviewer_ids} />
                    </div>

                    {!snapshot && (
                        <div className="rounded-md border bg-status-pending-bg p-3 text-sm text-status-pending">
                            Lock a representative corpus before screening.
                        </div>
                    )}

                    <Button type="submit" disabled={blocked || form.processing}>
                        <PlayCircle className="size-4" />
                        Start screening
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

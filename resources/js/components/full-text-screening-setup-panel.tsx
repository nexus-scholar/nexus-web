import { useForm } from '@inertiajs/react';
import { PlayCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';
import { Badge } from '@/components/ui/badge';
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
import type { FullTextScreeningReadiness, ScreeningReviewer } from '@/types';

type FullTextScreeningSetupForm = {
    name: string;
    required_reviewer_count: number;
    reviewer_ids: number[];
};

type FullTextScreeningSetupPanelProps = {
    actionUrl: string;
    availableReviewers: ScreeningReviewer[];
    defaultRequiredReviewerCount: number;
    disabled?: boolean;
    readiness: FullTextScreeningReadiness;
};

export function FullTextScreeningSetupPanel({
    actionUrl,
    availableReviewers,
    defaultRequiredReviewerCount,
    disabled = false,
    readiness,
}: FullTextScreeningSetupPanelProps) {
    const form = useForm<FullTextScreeningSetupForm>({
        name: 'Full-text screening',
        required_reviewer_count: defaultRequiredReviewerCount,
        reviewer_ids: availableReviewers.map((reviewer) => reviewer.id),
    });
    const blocked =
        disabled ||
        !readiness.ready ||
        readiness.counts.screenable === 0 ||
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
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle>Start full-text screening</CardTitle>
                        <CardDescription>
                            Assign records with successful artifacts to human
                            reviewers.
                        </CardDescription>
                    </div>
                    <Badge
                        className={
                            readiness.ready
                                ? 'border-transparent bg-status-include-bg text-status-include'
                                : 'border-transparent bg-status-pending-bg text-status-pending'
                        }
                    >
                        {readiness.ready ? 'Ready' : 'Blocked'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <form className="space-y-5" onSubmit={submit}>
                    <div className="grid gap-3 md:grid-cols-4">
                        <Metric
                            label="Screenable"
                            value={readiness.counts.screenable}
                        />
                        <Metric
                            label="Follow-up"
                            value={readiness.follow_up.total}
                        />
                        <Metric
                            label="Retrieved"
                            value={
                                readiness.full_text_batch?.success_count ?? 0
                            }
                        />
                        <Metric
                            label="Policy"
                            value={
                                readiness.full_text_batch?.status_label ??
                                'No batch'
                            }
                        />
                    </div>

                    {readiness.blockers.length > 0 && (
                        <div className="rounded-md border border-status-conflict/25 bg-status-conflict-bg p-3 text-sm text-status-conflict">
                            <div className="font-medium">
                                Full-text screening blockers
                            </div>
                            <ul className="mt-2 space-y-1">
                                {readiness.blockers.map((blocker) => (
                                    <li key={blocker}>{blocker}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem]">
                        <div className="space-y-2">
                            <Label htmlFor="full-text-screening-name">
                                Batch name
                            </Label>
                            <Input
                                id="full-text-screening-name"
                                value={form.data.name}
                                onChange={(event) => {
                                    form.setData('name', event.target.value);
                                }}
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="full-text-reviewer-count">
                                Required reviewers
                            </Label>
                            <Input
                                id="full-text-reviewer-count"
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

                    <Button type="submit" disabled={blocked || form.processing}>
                        <PlayCircle className="size-4" />
                        Start full-text screening
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-xl leading-none font-semibold tabular-nums">
                {value}
            </div>
        </div>
    );
}

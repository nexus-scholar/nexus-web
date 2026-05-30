import { useForm } from '@inertiajs/react';
import { Check, CircleHelp, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { DecisionBadge } from '@/components/decision-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { ScreeningDecision, ScreeningSelectedAssignment } from '@/types';

type DecisionForm = {
    decision: ScreeningDecision | '';
    reason: string;
    evidence: string;
    uncertainty: string;
    exclusion_basis: string;
};

type ScreeningDecisionPanelProps = {
    assignment: ScreeningSelectedAssignment | null;
};

const choices: Array<{
    value: ScreeningDecision;
    label: string;
    icon: typeof Check;
    description: string;
}> = [
    {
        value: 'include',
        label: 'Include',
        icon: Check,
        description: 'Appears eligible from title and abstract.',
    },
    {
        value: 'needs_review',
        label: 'Maybe',
        icon: CircleHelp,
        description: 'Eligibility is uncertain from available metadata.',
    },
    {
        value: 'exclude',
        label: 'Exclude',
        icon: X,
        description: 'Does not meet title and abstract criteria.',
    },
];

export function ScreeningDecisionPanel({
    assignment,
}: ScreeningDecisionPanelProps) {
    const existing = assignment?.decision;
    const form = useForm<DecisionForm>({
        decision: existing?.decision ?? '',
        evidence: existing?.evidence.join('\n') ?? '',
        exclusion_basis: existing?.exclusion_basis.join('\n') ?? '',
        reason: existing?.reason ?? '',
        uncertainty: existing?.uncertainty.join('\n') ?? '',
    });

    if (!assignment) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Decision</CardTitle>
                    <CardDescription>
                        Select an assignment to record a decision.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(assignment.decision_url, { preserveScroll: true });
    };
    const lockMessage = assignment.can_record_decision
        ? null
        : decisionLockMessage(assignment.status);

    return (
        <Card>
            <CardHeader className="space-y-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle>Decision</CardTitle>
                        <CardDescription>
                            {lockMessage
                                ? 'Review the recorded title and abstract decision.'
                                : 'Record a title and abstract decision with rationale.'}
                        </CardDescription>
                    </div>
                    <DecisionBadge
                        decision={assignment.decision?.decision ?? null}
                        label={assignment.decision?.decision_label}
                    />
                </div>
            </CardHeader>
            <CardContent>
                {lockMessage && (
                    <div className="mb-5 rounded-md border border-status-audit/30 bg-status-audit-bg p-3 text-sm text-status-audit">
                        <div className="font-medium">Decision locked</div>
                        <div className="mt-1 text-status-audit/85">
                            {lockMessage}
                        </div>
                    </div>
                )}
                <form className="space-y-5" onSubmit={submit}>
                    <div className="grid gap-2 md:grid-cols-3">
                        {choices.map((choice) => {
                            const Icon = choice.icon;
                            const selected =
                                form.data.decision === choice.value;

                            return (
                                <button
                                    key={choice.value}
                                    type="button"
                                    disabled={!assignment.can_record_decision}
                                    className={cn(
                                        'rounded-md border bg-card p-3 text-left shadow-xs transition hover:bg-accent',
                                        selected &&
                                            'border-brand bg-brand-muted text-brand-muted-foreground',
                                    )}
                                    onClick={() => {
                                        form.setData('decision', choice.value);
                                    }}
                                >
                                    <span className="flex items-center gap-2 text-sm font-medium">
                                        <Icon className="size-4" />
                                        {choice.label}
                                    </span>
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        {choice.description}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    <InputError message={form.errors.decision} />

                    <div className="space-y-2">
                        <Label htmlFor="decision-reason">Rationale</Label>
                        <Textarea
                            id="decision-reason"
                            value={form.data.reason}
                            disabled={!assignment.can_record_decision}
                            rows={4}
                            onChange={(event) => {
                                form.setData('reason', event.target.value);
                            }}
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    <div className="grid gap-3 lg:grid-cols-3">
                        <NotesField
                            id="decision-evidence"
                            label="Evidence notes"
                            value={form.data.evidence}
                            disabled={!assignment.can_record_decision}
                            error={form.errors.evidence}
                            onChange={(value) => {
                                form.setData('evidence', value);
                            }}
                        />
                        <NotesField
                            id="decision-uncertainty"
                            label="Uncertainty"
                            value={form.data.uncertainty}
                            disabled={!assignment.can_record_decision}
                            error={form.errors.uncertainty}
                            onChange={(value) => {
                                form.setData('uncertainty', value);
                            }}
                        />
                        <NotesField
                            id="decision-exclusion"
                            label="Exclusion basis"
                            value={form.data.exclusion_basis}
                            disabled={!assignment.can_record_decision}
                            error={form.errors.exclusion_basis}
                            onChange={(value) => {
                                form.setData('exclusion_basis', value);
                            }}
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={
                            !assignment.can_record_decision ||
                            form.processing ||
                            !form.data.decision
                        }
                    >
                        {assignment.can_record_decision
                            ? 'Record decision'
                            : 'Decision closed'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function decisionLockMessage(
    status: ScreeningSelectedAssignment['status'],
): string {
    if (status === 'conflict') {
        return 'This record has a disagreement and must be handled from conflict review.';
    }

    if (status === 'resolved') {
        return 'The team decision is finalized for this record.';
    }

    return 'This screening batch is no longer accepting reviewer decisions.';
}

function NotesField({
    disabled,
    error,
    id,
    label,
    onChange,
    value,
}: {
    id: string;
    label: string;
    value: string;
    disabled: boolean;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                value={value}
                disabled={disabled}
                rows={3}
                onChange={(event) => {
                    onChange(event.target.value);
                }}
            />
            <InputError message={error} />
        </div>
    );
}

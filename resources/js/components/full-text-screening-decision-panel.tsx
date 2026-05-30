import { useForm } from '@inertiajs/react';
import { Check, CircleHelp, FileCheck2, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { DecisionBadge } from '@/components/decision-badge';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    FullTextScreeningSelectedAssignment,
    ScreeningDecision,
} from '@/types';

type DecisionForm = {
    decision: ScreeningDecision | '';
    artifact_inspected: boolean;
    reason: string;
    evidence: string;
    uncertainty: string;
    exclusion_basis: string;
};

type FullTextScreeningDecisionPanelProps = {
    assignment: FullTextScreeningSelectedAssignment | null;
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
        description: 'The full text satisfies eligibility criteria.',
    },
    {
        value: 'needs_review',
        label: 'Maybe',
        icon: CircleHelp,
        description: 'Eligibility remains uncertain after full-text review.',
    },
    {
        value: 'exclude',
        label: 'Exclude',
        icon: X,
        description: 'The full text fails one or more criteria.',
    },
];

const exclusionReasons = [
    'Wrong population',
    'Wrong intervention or exposure',
    'Wrong comparator',
    'Wrong outcome',
    'Wrong study design',
    'Wrong publication type',
    'Duplicate report',
    'Language or date out of scope',
    'Other',
];

export function FullTextScreeningDecisionPanel({
    assignment,
}: FullTextScreeningDecisionPanelProps) {
    const existing = assignment?.decision;
    const form = useForm<DecisionForm>({
        artifact_inspected: Boolean(existing),
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
                        Select an assignment to record a full-text decision.
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

    const toggleExclusionReason = (reason: string) => {
        const lines = form.data.exclusion_basis
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);
        const next = lines.includes(reason)
            ? lines.filter((line) => line !== reason)
            : [...lines, reason];

        form.setData('exclusion_basis', next.join('\n'));
    };

    return (
        <Card>
            <CardHeader className="space-y-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle>Decision</CardTitle>
                        <CardDescription>
                            {lockMessage
                                ? 'Review the recorded full-text decision.'
                                : 'Record the final eligibility decision after inspecting the artifact.'}
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
                    <label className="flex items-start gap-3 rounded-md border bg-muted/20 p-3 text-sm">
                        <Checkbox
                            checked={form.data.artifact_inspected}
                            disabled={!assignment.can_record_decision}
                            onCheckedChange={(checked) => {
                                form.setData(
                                    'artifact_inspected',
                                    checked === true,
                                );
                            }}
                        />
                        <span className="min-w-0">
                            <span className="flex items-center gap-2 font-medium">
                                <FileCheck2 className="size-4" />
                                Artifact inspected
                            </span>
                            <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                Confirm the linked full-text artifact was opened
                                or downloaded before submitting.
                            </span>
                        </span>
                    </label>
                    <InputError message={form.errors.artifact_inspected} />

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
                        <Label htmlFor="full-text-decision-reason">
                            Rationale
                        </Label>
                        <Textarea
                            id="full-text-decision-reason"
                            value={form.data.reason}
                            disabled={!assignment.can_record_decision}
                            rows={4}
                            onChange={(event) => {
                                form.setData('reason', event.target.value);
                            }}
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="full-text-decision-exclusion">
                                Exclusion reasons
                            </Label>
                            <Badge variant="outline">
                                Required for exclude
                            </Badge>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {exclusionReasons.map((reason) => {
                                const selected = form.data.exclusion_basis
                                    .split('\n')
                                    .includes(reason);

                                return (
                                    <Button
                                        key={reason}
                                        type="button"
                                        size="sm"
                                        variant={
                                            selected ? 'default' : 'outline'
                                        }
                                        disabled={
                                            !assignment.can_record_decision
                                        }
                                        onClick={() => {
                                            toggleExclusionReason(reason);
                                        }}
                                    >
                                        {reason}
                                    </Button>
                                );
                            })}
                        </div>
                        <Textarea
                            id="full-text-decision-exclusion"
                            value={form.data.exclusion_basis}
                            disabled={!assignment.can_record_decision}
                            rows={3}
                            onChange={(event) => {
                                form.setData(
                                    'exclusion_basis',
                                    event.target.value,
                                );
                            }}
                        />
                        <InputError message={form.errors.exclusion_basis} />
                    </div>

                    <div className="grid gap-3 lg:grid-cols-2">
                        <NotesField
                            id="full-text-decision-evidence"
                            label="Evidence notes"
                            value={form.data.evidence}
                            disabled={!assignment.can_record_decision}
                            error={form.errors.evidence}
                            onChange={(value) => {
                                form.setData('evidence', value);
                            }}
                        />
                        <NotesField
                            id="full-text-decision-uncertainty"
                            label="Uncertainty"
                            value={form.data.uncertainty}
                            disabled={!assignment.can_record_decision}
                            error={form.errors.uncertainty}
                            onChange={(value) => {
                                form.setData('uncertainty', value);
                            }}
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={
                            !assignment.can_record_decision ||
                            form.processing ||
                            !form.data.decision ||
                            !form.data.artifact_inspected
                        }
                    >
                        {assignment.can_record_decision
                            ? 'Record full-text decision'
                            : 'Decision closed'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function decisionLockMessage(
    status: FullTextScreeningSelectedAssignment['status'],
): string {
    if (status === 'conflict') {
        return 'This record has a disagreement and must be handled from conflict review.';
    }

    if (status === 'resolved') {
        return 'The team decision is finalized for this full text.';
    }

    return 'This full-text screening batch is no longer accepting reviewer decisions.';
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

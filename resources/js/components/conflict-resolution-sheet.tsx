import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DecisionBadge } from '@/components/decision-badge';
import InputError from '@/components/input-error';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { ScreeningConflict, ScreeningDecision } from '@/types';

type ConflictResolutionForm = {
    decision: ScreeningDecision | '';
    reason: string;
    evidence: string;
    uncertainty: string;
    exclusion_basis: string;
};

type ConflictResolutionSheetProps = {
    conflict: ScreeningConflict | null;
    canResolve: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const decisions: Array<{ value: ScreeningDecision; label: string }> = [
    { value: 'include', label: 'Include' },
    { value: 'needs_review', label: 'Maybe' },
    { value: 'exclude', label: 'Exclude' },
];

export function ConflictResolutionSheet({
    canResolve,
    conflict,
    onOpenChange,
    open,
}: ConflictResolutionSheetProps) {
    const form = useForm<ConflictResolutionForm>({
        decision: conflict?.resolved_decision?.decision ?? '',
        evidence: conflict?.resolved_decision?.evidence.join('\n') ?? '',
        exclusion_basis:
            conflict?.resolved_decision?.exclusion_basis.join('\n') ?? '',
        reason: conflict?.resolution_reason ?? '',
        uncertainty: conflict?.resolved_decision?.uncertainty.join('\n') ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (conflict) {
            form.post(conflict.resolve_url, { preserveScroll: true });
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-[min(100vw,48rem)] gap-0 overflow-hidden p-0 sm:max-w-3xl"
            >
                <SheetHeader className="border-b px-5 py-4 pr-12">
                    <SheetTitle>Screening conflict</SheetTitle>
                    <SheetDescription>
                        Source decisions, rationale, and adjudication.
                    </SheetDescription>
                </SheetHeader>

                <div className="h-full overflow-y-auto px-5 py-5">
                    {!conflict ? (
                        <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                            No conflict is selected.
                        </div>
                    ) : (
                        <div className="space-y-5">
                            <section className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <ScreeningStatusBadge
                                        status={conflict.status}
                                        label={conflict.status_label}
                                    />
                                    {conflict.work.year && (
                                        <span className="text-sm text-muted-foreground">
                                            {conflict.work.year}
                                        </span>
                                    )}
                                </div>
                                <h3 className="text-base leading-6 font-medium">
                                    {conflict.work.title}
                                </h3>
                                <p className="max-h-48 overflow-auto rounded-md border bg-muted/20 p-3 text-sm leading-6 text-muted-foreground">
                                    {conflict.work.abstract ??
                                        'No abstract recorded.'}
                                </p>
                            </section>

                            <section className="space-y-2">
                                <h3 className="text-sm font-medium">
                                    Source decisions
                                </h3>
                                <div className="space-y-2">
                                    {conflict.source_decisions.map(
                                        (decision) => (
                                            <article
                                                key={decision.id}
                                                className="rounded-md border bg-card p-3 shadow-xs"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div className="text-sm font-medium">
                                                        {
                                                            decision.decided_by
                                                                .name
                                                        }
                                                    </div>
                                                    <DecisionBadge
                                                        decision={
                                                            decision.decision
                                                        }
                                                        label={
                                                            decision.decision_label
                                                        }
                                                    />
                                                </div>
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {decision.reason ??
                                                        'No rationale recorded.'}
                                                </p>
                                            </article>
                                        ),
                                    )}
                                </div>
                            </section>

                            <form className="space-y-4" onSubmit={submit}>
                                <div className="space-y-2">
                                    <Label>Resolution decision</Label>
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {decisions.map((decision) => (
                                            <Button
                                                key={decision.value}
                                                type="button"
                                                variant={
                                                    form.data.decision ===
                                                    decision.value
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={
                                                    !canResolve ||
                                                    conflict.status !== 'open'
                                                }
                                                onClick={() => {
                                                    form.setData(
                                                        'decision',
                                                        decision.value,
                                                    );
                                                }}
                                            >
                                                {decision.label}
                                            </Button>
                                        ))}
                                    </div>
                                    <InputError
                                        message={form.errors.decision}
                                    />
                                </div>

                                <NotesField
                                    id="conflict-reason"
                                    label="Audit reason"
                                    value={form.data.reason}
                                    disabled={
                                        !canResolve ||
                                        conflict.status !== 'open'
                                    }
                                    error={form.errors.reason}
                                    rows={4}
                                    onChange={(value) => {
                                        form.setData('reason', value);
                                    }}
                                />
                                <NotesField
                                    id="conflict-evidence"
                                    label="Evidence notes"
                                    value={form.data.evidence}
                                    disabled={
                                        !canResolve ||
                                        conflict.status !== 'open'
                                    }
                                    error={form.errors.evidence}
                                    onChange={(value) => {
                                        form.setData('evidence', value);
                                    }}
                                />
                                <NotesField
                                    id="conflict-uncertainty"
                                    label="Uncertainty"
                                    value={form.data.uncertainty}
                                    disabled={
                                        !canResolve ||
                                        conflict.status !== 'open'
                                    }
                                    error={form.errors.uncertainty}
                                    onChange={(value) => {
                                        form.setData('uncertainty', value);
                                    }}
                                />
                                <NotesField
                                    id="conflict-exclusion"
                                    label="Exclusion basis"
                                    value={form.data.exclusion_basis}
                                    disabled={
                                        !canResolve ||
                                        conflict.status !== 'open'
                                    }
                                    error={form.errors.exclusion_basis}
                                    onChange={(value) => {
                                        form.setData('exclusion_basis', value);
                                    }}
                                />

                                <Button
                                    type="submit"
                                    disabled={
                                        !canResolve ||
                                        conflict.status !== 'open' ||
                                        form.processing ||
                                        !form.data.decision
                                    }
                                >
                                    Resolve conflict
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function NotesField({
    disabled,
    error,
    id,
    label,
    onChange,
    rows = 3,
    value,
}: {
    id: string;
    label: string;
    value: string;
    disabled: boolean;
    error?: string;
    rows?: number;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                value={value}
                disabled={disabled}
                rows={rows}
                onChange={(event) => {
                    onChange(event.target.value);
                }}
            />
            <InputError message={error} />
        </div>
    );
}

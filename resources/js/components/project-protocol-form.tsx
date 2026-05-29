import type { InertiaFormProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { ProviderTagSelector } from '@/components/provider-tag-selector';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { ReviewType } from '@/types';

export type ReviewTypeOption = {
    value: ReviewType;
    label: string;
};

export type ProjectProtocolFormData = {
    intent: 'save' | 'complete';
    title: string;
    review_type: ReviewType;
    research_question: string;
    background: string;
    inclusion_criteria: string;
    exclusion_criteria: string;
    target_providers: string;
    date_range_start: string;
    date_range_end: string;
    no_date_limit: boolean;
    language_policy: string;
    min_reviewer_count: number;
    ai_screening_policy: 'human_only' | 'ai_assisted' | 'ai_excluded';
    full_text_policy:
        | 'optional'
        | 'required_for_inclusion'
        | 'manual_uploads_only';
    audit_reason: string;
};

type ProjectProtocolFormProps = {
    canCompleteProtocol: boolean;
    canUpdateProtocol: boolean;
    form: InertiaFormProps<ProjectProtocolFormData>;
    formErrors: Record<string, string | undefined>;
    isLocked: boolean;
    reviewTypes: ReviewTypeOption[];
    onSubmitIntent: (intent: ProjectProtocolFormData['intent']) => void;
};

export function ProjectProtocolForm({
    canCompleteProtocol,
    canUpdateProtocol,
    form,
    formErrors,
    isLocked,
    onSubmitIntent,
    reviewTypes,
}: ProjectProtocolFormProps) {
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                onSubmitIntent('save');
            }}
            className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]"
        >
            <section className="space-y-4">
                <ReviewDefinitionCard
                    disabled={!canUpdateProtocol}
                    form={form}
                    reviewTypes={reviewTypes}
                />
                <EligibilityCriteriaCard
                    disabled={!canUpdateProtocol}
                    form={form}
                />
                <SearchReadinessCard
                    disabled={!canUpdateProtocol}
                    form={form}
                />
            </section>

            <aside className="space-y-4">
                <ProtocolActionsCard
                    canCompleteProtocol={canCompleteProtocol}
                    canUpdateProtocol={canUpdateProtocol}
                    form={form}
                    formErrors={formErrors}
                    isLocked={isLocked}
                    onSubmitIntent={onSubmitIntent}
                />
            </aside>
        </form>
    );
}

function ReviewDefinitionCard({
    disabled,
    form,
    reviewTypes,
}: {
    disabled: boolean;
    form: InertiaFormProps<ProjectProtocolFormData>;
    reviewTypes: ReviewTypeOption[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Review definition</CardTitle>
                <CardDescription>
                    These fields anchor the project and future search runs.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="protocol-title">Title</Label>
                    <Input
                        id="protocol-title"
                        value={form.data.title}
                        disabled={disabled}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                    <InputError message={form.errors.title} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="review-type">Review type</Label>
                    <Select
                        value={form.data.review_type}
                        disabled={disabled}
                        onValueChange={(value: ReviewType) =>
                            form.setData('review_type', value)
                        }
                    >
                        <SelectTrigger id="review-type" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {reviewTypes.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.review_type} />
                </div>

                <ProtocolTextarea
                    id="research-question"
                    label="Research question"
                    value={form.data.research_question}
                    disabled={disabled}
                    error={form.errors.research_question}
                    onChange={(value) =>
                        form.setData('research_question', value)
                    }
                />

                <ProtocolTextarea
                    id="background"
                    label="Background and rationale"
                    value={form.data.background}
                    disabled={disabled}
                    error={form.errors.background}
                    onChange={(value) => form.setData('background', value)}
                />
            </CardContent>
        </Card>
    );
}

function EligibilityCriteriaCard({
    disabled,
    form,
}: {
    disabled: boolean;
    form: InertiaFormProps<ProjectProtocolFormData>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Eligibility criteria</CardTitle>
                <CardDescription>
                    Define what enters and leaves the evidence corpus.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <ProtocolTextarea
                    id="inclusion-criteria"
                    label="Inclusion criteria"
                    value={form.data.inclusion_criteria}
                    disabled={disabled}
                    error={form.errors.inclusion_criteria}
                    onChange={(value) =>
                        form.setData('inclusion_criteria', value)
                    }
                />
                <ProtocolTextarea
                    id="exclusion-criteria"
                    label="Exclusion criteria"
                    value={form.data.exclusion_criteria}
                    disabled={disabled}
                    error={form.errors.exclusion_criteria}
                    onChange={(value) =>
                        form.setData('exclusion_criteria', value)
                    }
                />
            </CardContent>
        </Card>
    );
}

function SearchReadinessCard({
    disabled,
    form,
}: {
    disabled: boolean;
    form: InertiaFormProps<ProjectProtocolFormData>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Search readiness</CardTitle>
                <CardDescription>
                    Provider, date, language, reviewer, AI, and full-text
                    policies.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="grid gap-2">
                    <Label id="target-providers-label">Target providers</Label>
                    <ProviderTagSelector
                        labelId="target-providers-label"
                        value={form.data.target_providers}
                        disabled={disabled}
                        onChange={(value) =>
                            form.setData('target_providers', value)
                        }
                    />
                    <InputError message={form.errors.target_providers} />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="date-start">Date range start</Label>
                        <Input
                            id="date-start"
                            type="date"
                            value={form.data.date_range_start}
                            disabled={disabled || form.data.no_date_limit}
                            onChange={(event) =>
                                form.setData(
                                    'date_range_start',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.date_range_start} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="date-end">Date range end</Label>
                        <Input
                            id="date-end"
                            type="date"
                            value={form.data.date_range_end}
                            disabled={disabled || form.data.no_date_limit}
                            onChange={(event) =>
                                form.setData(
                                    'date_range_end',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.date_range_end} />
                    </div>
                </div>

                <label className="flex items-start gap-3 rounded-md border bg-muted/30 p-3 text-sm">
                    <Checkbox
                        checked={form.data.no_date_limit}
                        disabled={disabled}
                        onCheckedChange={(checked) =>
                            form.setData('no_date_limit', checked === true)
                        }
                    />
                    <span>Use an explicit no-date-limit policy</span>
                </label>

                <div className="grid gap-2">
                    <Label htmlFor="language-policy">Language policy</Label>
                    <Input
                        id="language-policy"
                        value={form.data.language_policy}
                        disabled={disabled}
                        onChange={(event) =>
                            form.setData('language_policy', event.target.value)
                        }
                        placeholder="English-language records; translate non-English abstracts manually."
                    />
                    <InputError message={form.errors.language_policy} />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="reviewer-count">Reviewers</Label>
                        <Input
                            id="reviewer-count"
                            type="number"
                            min={1}
                            max={10}
                            value={form.data.min_reviewer_count}
                            disabled={disabled}
                            onChange={(event) =>
                                form.setData(
                                    'min_reviewer_count',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={form.errors.min_reviewer_count} />
                    </div>

                    <SelectField
                        id="ai-policy"
                        label="AI policy"
                        value={form.data.ai_screening_policy}
                        disabled={disabled}
                        options={[
                            ['human_only', 'Human only'],
                            ['ai_assisted', 'AI assisted'],
                            ['ai_excluded', 'AI excluded'],
                        ]}
                        error={form.errors.ai_screening_policy}
                        onChange={(value) =>
                            form.setData(
                                'ai_screening_policy',
                                value as ProjectProtocolFormData['ai_screening_policy'],
                            )
                        }
                    />

                    <SelectField
                        id="full-text-policy"
                        label="Full text"
                        value={form.data.full_text_policy}
                        disabled={disabled}
                        options={[
                            ['optional', 'Optional'],
                            [
                                'required_for_inclusion',
                                'Required for inclusion',
                            ],
                            ['manual_uploads_only', 'Manual uploads only'],
                        ]}
                        error={form.errors.full_text_policy}
                        onChange={(value) =>
                            form.setData(
                                'full_text_policy',
                                value as ProjectProtocolFormData['full_text_policy'],
                            )
                        }
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function ProtocolActionsCard({
    canCompleteProtocol,
    canUpdateProtocol,
    form,
    formErrors,
    isLocked,
    onSubmitIntent,
}: {
    canCompleteProtocol: boolean;
    canUpdateProtocol: boolean;
    form: InertiaFormProps<ProjectProtocolFormData>;
    formErrors: Record<string, string | undefined>;
    isLocked: boolean;
    onSubmitIntent: (intent: ProjectProtocolFormData['intent']) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Actions</CardTitle>
                <CardDescription>
                    Save drafts or mark the protocol complete.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {isLocked && (
                    <div className="grid gap-2">
                        <Label htmlFor="audit-reason">Audit reason</Label>
                        <Textarea
                            id="audit-reason"
                            value={form.data.audit_reason}
                            disabled={!canUpdateProtocol}
                            onChange={(event) =>
                                form.setData('audit_reason', event.target.value)
                            }
                            className="min-h-24"
                        />
                        <InputError message={form.errors.audit_reason} />
                    </div>
                )}
                <Button
                    type="submit"
                    disabled={!canUpdateProtocol || form.processing}
                    className="w-full"
                >
                    Save draft
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!canCompleteProtocol || form.processing}
                    className="w-full"
                    onClick={() => onSubmitIntent('complete')}
                >
                    Mark complete
                </Button>
                <InputError message={formErrors.protocol} />
                <InputError message={formErrors.missing_fields} />
            </CardContent>
        </Card>
    );
}

function ProtocolTextarea({
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
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                className="min-h-32"
            />
            <InputError message={error} />
        </div>
    );
}

function SelectField({
    disabled,
    error,
    id,
    label,
    onChange,
    options,
    value,
}: {
    id: string;
    label: string;
    value: string;
    disabled: boolean;
    options: Array<[string, string]>;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} disabled={disabled} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([optionValue, optionLabel]) => (
                        <SelectItem key={optionValue} value={optionValue}>
                            {optionLabel}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

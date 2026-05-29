import type { InertiaFormProps } from '@inertiajs/react';
import {
    Database,
    LockKeyhole,
    Play,
    Plus,
    Save,
    Search,
    Trash2,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { ProviderTagSelector } from '@/components/provider-tag-selector';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type ProjectSearchPlanQueryFormData = {
    id?: string;
    query_key: string;
    label: string;
    query: string;
    providers: string;
    year_from: string;
    year_to: string;
    result_limit: number;
    include_raw_data: boolean;
};

export type ProjectSearchPlanFormData = {
    default_providers: string;
    default_year_from: string;
    default_year_to: string;
    default_result_limit: number;
    include_raw_data: boolean;
    queries: ProjectSearchPlanQueryFormData[];
};

type ProjectSearchPlanFormProps = {
    canRunSearch: boolean;
    canUpdateSearchPlan: boolean;
    form: InertiaFormProps<ProjectSearchPlanFormData>;
    formErrors: Record<string, string | undefined>;
    isLocked: boolean;
    planStatusLabel: string;
    planVersion: number;
    runProcessing?: boolean;
    onRunAll: () => void;
    onSubmit: () => void;
};

export function ProjectSearchPlanForm({
    canRunSearch,
    canUpdateSearchPlan,
    form,
    formErrors,
    isLocked,
    onRunAll,
    onSubmit,
    planStatusLabel,
    planVersion,
    runProcessing = false,
}: ProjectSearchPlanFormProps) {
    const disabled = !canUpdateSearchPlan;

    const updateQuery = <Key extends keyof ProjectSearchPlanQueryFormData>(
        index: number,
        key: Key,
        value: ProjectSearchPlanQueryFormData[Key],
    ) => {
        const queries = form.data.queries.map((query, queryIndex) =>
            queryIndex === index ? { ...query, [key]: value } : query,
        );

        form.setData('queries', queries);
    };

    const addQuery = () => {
        const nextIndex = form.data.queries.length + 1;

        form.setData('queries', [
            ...form.data.queries,
            {
                query_key: `query-${nextIndex}`,
                label: `Supplementary search ${nextIndex}`,
                query: '',
                providers: form.data.default_providers,
                year_from: form.data.default_year_from,
                year_to: form.data.default_year_to,
                result_limit: form.data.default_result_limit,
                include_raw_data: form.data.include_raw_data,
            },
        ]);
    };

    const removeQuery = (index: number) => {
        if (form.data.queries.length <= 1) {
            return;
        }

        form.setData(
            'queries',
            form.data.queries.filter((_, queryIndex) => queryIndex !== index),
        );
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
            className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]"
        >
            <section className="space-y-4">
                <SearchDefaultsCard
                    disabled={disabled}
                    form={form}
                    formErrors={formErrors}
                />

                <QueryStrategyCard
                    disabled={disabled}
                    form={form}
                    formErrors={formErrors}
                    onAddQuery={addQuery}
                    onRemoveQuery={removeQuery}
                    onUpdateQuery={updateQuery}
                />
            </section>

            <aside className="space-y-4">
                <SearchPlanActionsCard
                    canRunSearch={canRunSearch}
                    canUpdateSearchPlan={canUpdateSearchPlan}
                    form={form}
                    formErrors={formErrors}
                    isLocked={isLocked}
                    planStatusLabel={planStatusLabel}
                    planVersion={planVersion}
                    runProcessing={runProcessing}
                    onRunAll={onRunAll}
                />
            </aside>
        </form>
    );
}

function SearchDefaultsCard({
    disabled,
    form,
    formErrors,
}: {
    disabled: boolean;
    form: InertiaFormProps<ProjectSearchPlanFormData>;
    formErrors: Record<string, string | undefined>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Search defaults</CardTitle>
                <CardDescription>
                    Provider, year, result-limit, and raw-payload defaults
                    inherited by new query rows.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="grid gap-2">
                    <Label id="default-providers-label">
                        Default providers
                    </Label>
                    <ProviderTagSelector
                        labelId="default-providers-label"
                        value={form.data.default_providers}
                        disabled={disabled}
                        onChange={(value) =>
                            form.setData('default_providers', value)
                        }
                    />
                    <InputError message={formErrors.default_providers} />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <YearInput
                        id="default-year-from"
                        label="Year from"
                        value={form.data.default_year_from}
                        disabled={disabled}
                        error={formErrors.default_year_from}
                        onChange={(value) =>
                            form.setData('default_year_from', value)
                        }
                    />
                    <YearInput
                        id="default-year-to"
                        label="Year to"
                        value={form.data.default_year_to}
                        disabled={disabled}
                        error={formErrors.default_year_to}
                        onChange={(value) =>
                            form.setData('default_year_to', value)
                        }
                    />
                    <NumberInput
                        id="default-result-limit"
                        label="Result limit"
                        value={form.data.default_result_limit}
                        min={1}
                        max={500}
                        disabled={disabled}
                        error={formErrors.default_result_limit}
                        onChange={(value) =>
                            form.setData('default_result_limit', value)
                        }
                    />
                </div>

                <label className="flex items-start gap-3 rounded-md border bg-muted/30 p-3 text-sm">
                    <Checkbox
                        checked={form.data.include_raw_data}
                        disabled={disabled}
                        onCheckedChange={(checked) =>
                            form.setData('include_raw_data', checked === true)
                        }
                    />
                    <span>Store raw provider payloads for audit review</span>
                </label>
                <InputError message={formErrors.include_raw_data} />
            </CardContent>
        </Card>
    );
}

function QueryStrategyCard({
    disabled,
    form,
    formErrors,
    onAddQuery,
    onRemoveQuery,
    onUpdateQuery,
}: {
    disabled: boolean;
    form: InertiaFormProps<ProjectSearchPlanFormData>;
    formErrors: Record<string, string | undefined>;
    onAddQuery: () => void;
    onRemoveQuery: (index: number) => void;
    onUpdateQuery: <Key extends keyof ProjectSearchPlanQueryFormData>(
        index: number,
        key: Key,
        value: ProjectSearchPlanQueryFormData[Key],
    ) => void;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div className="space-y-1">
                    <CardTitle>Query strategy</CardTitle>
                    <CardDescription>
                        Search strings that can be queued as background provider
                        jobs.
                    </CardDescription>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={disabled || form.data.queries.length >= 20}
                    onClick={onAddQuery}
                >
                    <Plus className="size-4" />
                    Add query
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                <InputError message={formErrors.queries} />
                {form.data.queries.map((query, index) => (
                    <QueryRow
                        key={query.id ?? `${query.query_key}-${index}`}
                        disabled={disabled}
                        formErrors={formErrors}
                        index={index}
                        query={query}
                        queryCount={form.data.queries.length}
                        onRemove={() => onRemoveQuery(index)}
                        onUpdate={(key, value) =>
                            onUpdateQuery(index, key, value)
                        }
                    />
                ))}
            </CardContent>
        </Card>
    );
}

function QueryRow({
    disabled,
    formErrors,
    index,
    onRemove,
    onUpdate,
    query,
    queryCount,
}: {
    disabled: boolean;
    formErrors: Record<string, string | undefined>;
    index: number;
    query: ProjectSearchPlanQueryFormData;
    queryCount: number;
    onRemove: () => void;
    onUpdate: <Key extends keyof ProjectSearchPlanQueryFormData>(
        key: Key,
        value: ProjectSearchPlanQueryFormData[Key],
    ) => void;
}) {
    const labelPrefix = `queries.${index}`;

    return (
        <div className="rounded-md border bg-muted/20 p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex items-center gap-2">
                    <div className="flex size-9 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                        <Search className="size-4" aria-hidden="true" />
                    </div>
                    <div>
                        <div className="text-sm font-medium">
                            Query {index + 1}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Provider-ready Boolean or keyword strategy.
                        </div>
                    </div>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={disabled || queryCount <= 1}
                    onClick={onRemove}
                    aria-label={`Remove query ${index + 1}`}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-[12rem_minmax(0,1fr)]">
                <div className="grid gap-2">
                    <Label htmlFor={`query-key-${index}`}>Query ID</Label>
                    <Input
                        id={`query-key-${index}`}
                        value={query.query_key}
                        disabled={disabled}
                        onChange={(event) =>
                            onUpdate('query_key', event.target.value)
                        }
                    />
                    <InputError
                        message={formErrors[`${labelPrefix}.query_key`]}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`query-label-${index}`}>Label</Label>
                    <Input
                        id={`query-label-${index}`}
                        value={query.label}
                        disabled={disabled}
                        onChange={(event) =>
                            onUpdate('label', event.target.value)
                        }
                    />
                    <InputError message={formErrors[`${labelPrefix}.label`]} />
                </div>
            </div>

            <div className="mt-4 grid gap-2">
                <Label htmlFor={`query-text-${index}`}>Search string</Label>
                <Textarea
                    id={`query-text-${index}`}
                    value={query.query}
                    disabled={disabled}
                    className="min-h-28 font-mono text-sm"
                    onChange={(event) => onUpdate('query', event.target.value)}
                />
                <InputError message={formErrors[`${labelPrefix}.query`]} />
            </div>

            <div className="mt-4 grid gap-2">
                <Label id={`query-providers-label-${index}`}>
                    Query providers
                </Label>
                <ProviderTagSelector
                    labelId={`query-providers-label-${index}`}
                    value={query.providers}
                    disabled={disabled}
                    onChange={(value) => onUpdate('providers', value)}
                />
                <InputError message={formErrors[`${labelPrefix}.providers`]} />
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-4">
                <YearInput
                    id={`query-year-from-${index}`}
                    label="Year from"
                    value={query.year_from}
                    disabled={disabled}
                    error={formErrors[`${labelPrefix}.year_from`]}
                    onChange={(value) => onUpdate('year_from', value)}
                />
                <YearInput
                    id={`query-year-to-${index}`}
                    label="Year to"
                    value={query.year_to}
                    disabled={disabled}
                    error={formErrors[`${labelPrefix}.year_to`]}
                    onChange={(value) => onUpdate('year_to', value)}
                />
                <NumberInput
                    id={`query-result-limit-${index}`}
                    label="Result limit"
                    value={query.result_limit}
                    min={1}
                    max={500}
                    disabled={disabled}
                    error={formErrors[`${labelPrefix}.result_limit`]}
                    onChange={(value) => onUpdate('result_limit', value)}
                />
                <label
                    className={cn(
                        'flex min-h-10 items-center gap-3 rounded-md border bg-background px-3 text-sm md:mt-6',
                        disabled && 'opacity-60',
                    )}
                >
                    <Checkbox
                        checked={query.include_raw_data}
                        disabled={disabled}
                        onCheckedChange={(checked) =>
                            onUpdate('include_raw_data', checked === true)
                        }
                    />
                    <span>Raw payloads</span>
                </label>
            </div>
            <InputError
                message={formErrors[`${labelPrefix}.include_raw_data`]}
            />
        </div>
    );
}

function SearchPlanActionsCard({
    canRunSearch,
    canUpdateSearchPlan,
    form,
    formErrors,
    isLocked,
    onRunAll,
    planStatusLabel,
    planVersion,
    runProcessing,
}: {
    canRunSearch: boolean;
    canUpdateSearchPlan: boolean;
    form: InertiaFormProps<ProjectSearchPlanFormData>;
    formErrors: Record<string, string | undefined>;
    isLocked: boolean;
    onRunAll: () => void;
    planStatusLabel: string;
    planVersion: number;
    runProcessing: boolean;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Plan control</CardTitle>
                <CardDescription>
                    Save the provider plan before dispatching searches.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 rounded-md border bg-muted/30 p-3 text-sm">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground">Status</span>
                        <Badge variant="outline">{planStatusLabel}</Badge>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground">Version</span>
                        <span className="font-medium">v{planVersion}</span>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground">Queries</span>
                        <span className="font-medium">
                            {form.data.queries.length}
                        </span>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground">Lock</span>
                        <span className="flex items-center gap-1 font-medium">
                            <LockKeyhole className="size-3.5 text-muted-foreground" />
                            {isLocked ? 'Locked' : 'Open'}
                        </span>
                    </div>
                </div>

                <Button
                    type="submit"
                    disabled={!canUpdateSearchPlan || form.processing}
                    className="w-full"
                >
                    <Save className="size-4" />
                    Save search plan
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    disabled={!canRunSearch || form.processing || runProcessing}
                    className="w-full justify-start"
                    aria-label={
                        canRunSearch
                            ? 'Run all queries'
                            : 'Run all queries blocked'
                    }
                    onClick={onRunAll}
                >
                    <Play className="size-4" />
                    Run all queries
                </Button>

                <div className="flex gap-2 rounded-md border bg-muted/30 p-3 text-xs leading-5 text-muted-foreground">
                    <Database className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Search runs are queued in the background. Open the run
                        page after dispatch to inspect provider progress.
                    </span>
                </div>

                <InputError message={formErrors.search_plan} />
            </CardContent>
        </Card>
    );
}

function YearInput({
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
            <Input
                id={id}
                inputMode="numeric"
                pattern="[0-9]*"
                value={value}
                disabled={disabled}
                placeholder="Any"
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} />
        </div>
    );
}

function NumberInput({
    disabled,
    error,
    id,
    label,
    max,
    min,
    onChange,
    value,
}: {
    id: string;
    label: string;
    value: number;
    min: number;
    max: number;
    disabled: boolean;
    error?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                min={min}
                max={max}
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(Number(event.target.value))}
            />
            <InputError message={error} />
        </div>
    );
}

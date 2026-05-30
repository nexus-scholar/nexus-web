import {
    BadgeAlert,
    CalendarRange,
    Database,
    FileWarning,
    Filter,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode, SelectHTMLAttributes } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { CorpusFilterOptions, CorpusFilters } from '@/types';

type CorpusFilterBarProps = {
    filters: CorpusFilters;
    options: CorpusFilterOptions;
    onApply: (filters: Partial<CorpusFilters>) => void;
    onReset: () => void;
};

export function CorpusFilterBar({
    filters,
    onApply,
    onReset,
    options,
}: CorpusFilterBarProps) {
    const activeFilters = activeFilterLabels(filters, options);
    const [expanded, setExpanded] = useState(false);

    return (
        <form
            className="rounded-lg border bg-card shadow-xs"
            onSubmit={(event) => {
                event.preventDefault();
                onApply(filtersFromForm(new FormData(event.currentTarget)));
            }}
        >
            <div className="flex flex-col gap-3 p-3 lg:flex-row lg:items-center">
                <Label className="sr-only" htmlFor="corpus-search">
                    Search
                </Label>
                <div className="relative min-w-0 flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        id="corpus-search"
                        name="q"
                        defaultValue={filters.q ?? ''}
                        placeholder="Search title or abstract"
                        className="h-9 pl-9"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setExpanded((value) => !value);
                        }}
                        aria-expanded={expanded}
                    >
                        <Filter className="size-4" />
                        Filters
                        {activeFilters.length > 0 && (
                            <Badge
                                variant="secondary"
                                className="ml-1 rounded-sm px-1.5"
                            >
                                {activeFilters.length}
                            </Badge>
                        )}
                    </Button>
                    <Button type="submit" size="sm">
                        Apply
                    </Button>
                    {activeFilters.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onReset}
                        >
                            <RotateCcw className="size-4" />
                            Reset
                        </Button>
                    )}
                </div>
            </div>

            {activeFilters.length > 0 && (
                <div className="flex flex-wrap gap-2 border-t px-3 py-2">
                    {activeFilters.map((filter) => (
                        <Badge
                            key={filter}
                            variant="outline"
                            className="bg-muted/40 font-normal"
                        >
                            {filter}
                        </Badge>
                    ))}
                </div>
            )}

            {expanded && (
                <div className="grid gap-3 border-t bg-muted/20 p-3 md:grid-cols-2 xl:grid-cols-4">
                    <Field
                        icon={<Database className="size-4" />}
                        label="Provider"
                    >
                        <NativeSelect
                            name="provider"
                            defaultValue={filters.provider ?? ''}
                        >
                            <option value="">All providers</option>
                            {options.providers.map((provider) => (
                                <option key={provider} value={provider}>
                                    {provider}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>

                    <Field
                        icon={<Search className="size-4" />}
                        label="Search query"
                    >
                        <NativeSelect
                            name="search_query"
                            defaultValue={filters.search_query ?? ''}
                        >
                            <option value="">All queries</option>
                            {options.search_queries.map((query) => (
                                <option key={query.id} value={query.id}>
                                    {query.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>

                    <Field
                        icon={<CalendarRange className="size-4" />}
                        label="Year range"
                    >
                        <div className="grid grid-cols-2 gap-2">
                            <Input
                                aria-label="Year from"
                                name="year_from"
                                defaultValue={filters.year_from ?? ''}
                                inputMode="numeric"
                                pattern="[0-9]*"
                                placeholder="From"
                            />
                            <Input
                                aria-label="Year to"
                                name="year_to"
                                defaultValue={filters.year_to ?? ''}
                                inputMode="numeric"
                                pattern="[0-9]*"
                                placeholder="To"
                            />
                        </div>
                    </Field>

                    <Field
                        icon={<Database className="size-4" />}
                        label="Identifier"
                    >
                        <NativeSelect
                            name="identifier"
                            defaultValue={filters.identifier ?? ''}
                        >
                            <option value="">Any identifier</option>
                            {options.identifiers.map((identifier) => (
                                <option key={identifier} value={identifier}>
                                    {identifier.toUpperCase()}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>

                    <Field
                        icon={<BadgeAlert className="size-4" />}
                        label="Duplicate status"
                    >
                        <NativeSelect
                            name="duplicate_status"
                            defaultValue={filters.duplicate_status}
                        >
                            {options.duplicate_statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>

                    <div className="grid gap-2 md:col-span-2 xl:col-span-3">
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <FileWarning className="size-4 text-muted-foreground" />
                            Metadata flags
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <CheckboxField
                                name="missing_abstract"
                                label="Missing abstract"
                                defaultChecked={filters.missing_abstract}
                            />
                            <CheckboxField
                                name="missing_identifier"
                                label="Missing identifier"
                                defaultChecked={filters.missing_identifier}
                            />
                            <CheckboxField
                                name="retracted"
                                label="Retracted"
                                defaultChecked={filters.retracted}
                            />
                        </div>
                    </div>
                </div>
            )}
        </form>
    );
}

function Field({
    children,
    icon,
    label,
}: {
    children: ReactNode;
    icon: ReactNode;
    label: string;
}) {
    return (
        <label className="grid gap-2">
            <span className="flex items-center gap-2 text-sm font-medium">
                <span className="text-muted-foreground">{icon}</span>
                {label}
            </span>
            {children}
        </label>
    );
}

function CheckboxField({
    defaultChecked,
    label,
    name,
}: {
    name: string;
    label: string;
    defaultChecked: boolean;
}) {
    return (
        <Label className="flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm shadow-xs">
            <Checkbox name={name} defaultChecked={defaultChecked} value="1" />
            <span>{label}</span>
        </Label>
    );
}

function NativeSelect({
    className,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            className={cn(
                'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

function activeFilterLabels(
    filters: CorpusFilters,
    options: CorpusFilterOptions,
): string[] {
    const labels: string[] = [];

    if (filters.q) {
        labels.push(`Search: ${filters.q}`);
    }

    if (filters.provider) {
        labels.push(`Provider: ${filters.provider}`);
    }

    if (filters.search_query) {
        labels.push(
            `Query: ${
                options.search_queries.find(
                    (query) => query.id === filters.search_query,
                )?.label ?? filters.search_query
            }`,
        );
    }

    if (filters.year_from || filters.year_to) {
        labels.push(
            `Years: ${filters.year_from ?? 'Any'}-${filters.year_to ?? 'Any'}`,
        );
    }

    if (filters.identifier) {
        labels.push(`ID: ${filters.identifier.toUpperCase()}`);
    }

    if (filters.duplicate_status !== 'all') {
        labels.push(
            options.duplicate_statuses.find(
                (status) => status.value === filters.duplicate_status,
            )?.label ?? 'Duplicate filter',
        );
    }

    if (filters.missing_abstract) {
        labels.push('Missing abstract');
    }

    if (filters.missing_identifier) {
        labels.push('Missing identifier');
    }

    if (filters.retracted) {
        labels.push('Retracted');
    }

    return labels;
}

function filtersFromForm(formData: FormData): Partial<CorpusFilters> {
    const value = (key: string): string | null => {
        const formValue = formData.get(key);

        if (typeof formValue !== 'string') {
            return null;
        }

        const trimmed = formValue.trim();

        return trimmed === '' ? null : trimmed;
    };

    const integerValue = (key: string): number | null => {
        const formValue = value(key);

        return formValue === null ? null : Number(formValue);
    };

    return {
        duplicate_status:
            (value('duplicate_status') as CorpusFilters['duplicate_status']) ??
            'all',
        identifier: value('identifier'),
        missing_abstract: formData.has('missing_abstract'),
        missing_identifier: formData.has('missing_identifier'),
        provider: value('provider'),
        q: value('q'),
        retracted: formData.has('retracted'),
        search_query: value('search_query'),
        work: null,
        year_from: integerValue('year_from'),
        year_to: integerValue('year_to'),
    };
}

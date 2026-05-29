import { RotateCcw, Search } from 'lucide-react';
import type { ReactNode, SelectHTMLAttributes } from 'react';
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
    return (
        <form
            className="rounded-lg border bg-card p-4 shadow-xs"
            onSubmit={(event) => {
                event.preventDefault();
                onApply(filtersFromForm(new FormData(event.currentTarget)));
            }}
        >
            <div className="grid gap-3 lg:grid-cols-[minmax(14rem,1.4fr)_repeat(5,minmax(8rem,1fr))]">
                <Field label="Search">
                    <Input
                        name="q"
                        defaultValue={filters.q ?? ''}
                        placeholder="Title or abstract"
                    />
                </Field>

                <Field label="Provider">
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

                <Field label="Search query">
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

                <Field label="Year from">
                    <Input
                        name="year_from"
                        defaultValue={filters.year_from ?? ''}
                        inputMode="numeric"
                        pattern="[0-9]*"
                        placeholder="Any"
                    />
                </Field>

                <Field label="Year to">
                    <Input
                        name="year_to"
                        defaultValue={filters.year_to ?? ''}
                        inputMode="numeric"
                        pattern="[0-9]*"
                        placeholder="Any"
                    />
                </Field>

                <Field label="Identifier">
                    <NativeSelect
                        name="identifier"
                        defaultValue={filters.identifier ?? ''}
                    >
                        <option value="">Any</option>
                        {options.identifiers.map((identifier) => (
                            <option key={identifier} value={identifier}>
                                {identifier.toUpperCase()}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
            </div>

            <div className="mt-4 flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Duplicate status">
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

                <div className="flex shrink-0 gap-2">
                    <Button type="submit">
                        <Search className="size-4" />
                        Apply
                    </Button>
                    <Button type="button" variant="outline" onClick={onReset}>
                        <RotateCcw className="size-4" />
                        Reset
                    </Button>
                </div>
            </div>
        </form>
    );
}

function Field({ children, label }: { children: ReactNode; label: string }) {
    return (
        <label className="grid gap-2">
            <span className="text-sm font-medium">{label}</span>
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
        <Label className="flex min-h-10 items-center gap-3 rounded-md border bg-background px-3 text-sm">
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
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
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
        q: value('q'),
        provider: value('provider'),
        search_query: value('search_query'),
        year_from: integerValue('year_from'),
        year_to: integerValue('year_to'),
        identifier: value('identifier'),
        duplicate_status:
            (value('duplicate_status') as CorpusFilters['duplicate_status']) ??
            'all',
        missing_abstract: formData.has('missing_abstract'),
        missing_identifier: formData.has('missing_identifier'),
        retracted: formData.has('retracted'),
        work: null,
    };
}

import { Check, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type ProviderOption = {
    value: string;
    label: string;
    description: string;
    enabledByDefault: boolean;
};

export const AVAILABLE_PROVIDER_OPTIONS: ProviderOption[] = [
    {
        value: 'openalex',
        label: 'OpenAlex',
        description: 'Broad scholarly metadata and citation traversal.',
        enabledByDefault: true,
    },
    {
        value: 'crossref',
        label: 'Crossref',
        description: 'DOI-centered scholarly metadata.',
        enabledByDefault: true,
    },
    {
        value: 'semantic_scholar',
        label: 'Semantic Scholar',
        description: 'AI and citation-aware literature metadata.',
        enabledByDefault: true,
    },
    {
        value: 'arxiv',
        label: 'arXiv',
        description: 'Preprint discovery for technical fields.',
        enabledByDefault: true,
    },
    {
        value: 'pubmed',
        label: 'PubMed',
        description: 'Biomedical literature metadata.',
        enabledByDefault: true,
    },
    {
        value: 'doaj',
        label: 'DOAJ',
        description: 'Open-access journal articles.',
        enabledByDefault: true,
    },
    {
        value: 'ieee',
        label: 'IEEE',
        description: 'Engineering and computing literature.',
        enabledByDefault: false,
    },
];

type ProviderTagSelectorProps = {
    disabled?: boolean;
    labelId: string;
    onChange: (value: string) => void;
    value: string;
};

export function ProviderTagSelector({
    disabled = false,
    labelId,
    onChange,
    value,
}: ProviderTagSelectorProps) {
    const selected = parseProviderValue(value);
    const selectedSet = new Set(selected);

    const setSelected = (nextSelected: string[]) => {
        onChange(formatProviderValue(nextSelected));
    };

    const toggleProvider = (provider: string) => {
        if (disabled) {
            return;
        }

        setSelected(
            selectedSet.has(provider)
                ? selected.filter((value) => value !== provider)
                : [...selected, provider],
        );
    };

    return (
        <div
            role="group"
            aria-labelledby={labelId}
            aria-disabled={disabled}
            className="space-y-3"
            data-test="provider-tag-selector"
        >
            <div
                className={cn(
                    'flex min-h-11 flex-wrap items-center gap-2 rounded-md border bg-background p-2 shadow-xs',
                    disabled && 'opacity-60',
                )}
            >
                {selected.length === 0 ? (
                    <span className="px-1 text-sm text-muted-foreground">
                        Choose one or more providers.
                    </span>
                ) : (
                    selected.map((provider) => {
                        const option = providerOption(provider);
                        const label = option?.label ?? provider;

                        return (
                            <Button
                                key={provider}
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={disabled}
                                aria-label={`Remove ${label}`}
                                className="h-7 rounded-full px-2 text-xs"
                                onClick={() => toggleProvider(provider)}
                            >
                                <Check className="size-3" />
                                {label}
                                <X className="size-3 opacity-60" />
                            </Button>
                        );
                    })
                )}
            </div>

            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                {AVAILABLE_PROVIDER_OPTIONS.map((option) => {
                    const selected = selectedSet.has(option.value);
                    const availabilityLabel = option.enabledByDefault
                        ? 'Available by default'
                        : 'Requires credentials';

                    return (
                        <Button
                            key={option.value}
                            type="button"
                            variant={selected ? 'default' : 'outline'}
                            disabled={disabled}
                            aria-pressed={selected}
                            aria-label={`${option.label} ${availabilityLabel}`}
                            className={cn(
                                'h-auto justify-start rounded-md px-3 py-2 text-left whitespace-normal',
                                selected &&
                                    'bg-brand text-brand-foreground hover:bg-brand/90',
                            )}
                            onClick={() => toggleProvider(option.value)}
                        >
                            {selected ? (
                                <Check className="mt-0.5 size-4" />
                            ) : (
                                <Plus className="mt-0.5 size-4" />
                            )}
                            <span className="grid gap-0.5">
                                <span>{option.label}</span>
                                <span
                                    className={cn(
                                        'text-xs font-normal',
                                        selected
                                            ? 'text-brand-foreground/80'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {availabilityLabel}
                                </span>
                            </span>
                        </Button>
                    );
                })}
            </div>
        </div>
    );
}

export function parseProviderValue(value: string): string[] {
    const available = new Set(
        AVAILABLE_PROVIDER_OPTIONS.map((option) => option.value),
    );

    return Array.from(
        new Set(
            value
                .split(',')
                .map((provider) => normalizeProvider(provider))
                .filter((provider) => available.has(provider)),
        ),
    );
}

export function formatProviderValue(providers: string[]): string {
    return providers.join(', ');
}

function providerOption(provider: string): ProviderOption | undefined {
    return AVAILABLE_PROVIDER_OPTIONS.find(
        (option) => option.value === provider,
    );
}

function normalizeProvider(provider: string): string {
    return provider.trim().toLowerCase().replace(/-/g, '_');
}

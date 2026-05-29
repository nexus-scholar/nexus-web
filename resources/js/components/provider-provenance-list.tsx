import { Badge } from '@/components/ui/badge';
import type { CorpusProvider, CorpusProvenance } from '@/types';

type ProviderProvenanceListProps = {
    providers: CorpusProvider[];
    provenance: CorpusProvenance[];
};

export function ProviderProvenanceList({
    providers,
    provenance,
}: ProviderProvenanceListProps) {
    return (
        <div className="space-y-4">
            <section className="space-y-2">
                <h3 className="text-sm font-medium">Provider sightings</h3>
                {providers.length === 0 ? (
                    <EmptyFact>No provider sightings recorded.</EmptyFact>
                ) : (
                    <div className="grid gap-2">
                        {providers.map((provider) => (
                            <div
                                key={`${provider.provider_alias}-${provider.provider_work_id}`}
                                className="rounded-md border bg-muted/20 p-3 text-sm"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {provider.provider_alias}
                                    </Badge>
                                    {provider.provider_work_id && (
                                        <span className="font-mono text-xs break-all text-muted-foreground">
                                            {provider.provider_work_id}
                                        </span>
                                    )}
                                </div>
                                <div className="mt-2 text-xs text-muted-foreground">
                                    First seen{' '}
                                    {formatDate(provider.first_seen_at)}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            <section className="space-y-2">
                <h3 className="text-sm font-medium">Query provenance</h3>
                {provenance.length === 0 ? (
                    <EmptyFact>No query provenance recorded.</EmptyFact>
                ) : (
                    <div className="grid gap-2">
                        {provenance.map((item, index) => (
                            <div
                                key={`${item.search_query_id}-${item.provider_alias}-${index}`}
                                className="rounded-md border bg-background p-3 text-sm"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {item.search_query_label}
                                    </span>
                                    {item.provider_alias && (
                                        <Badge variant="outline">
                                            {item.provider_alias}
                                        </Badge>
                                    )}
                                </div>
                                <div className="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                    <span>
                                        Rank{' '}
                                        <strong className="font-medium text-foreground">
                                            {item.rank ?? 'Not recorded'}
                                        </strong>
                                    </span>
                                    <span>Seen {formatDate(item.seen_at)}</span>
                                </div>
                                {item.provider_work_id && (
                                    <div className="mt-2 font-mono text-xs break-all text-muted-foreground">
                                        {item.provider_work_id}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

function EmptyFact({ children }: { children: string }) {
    return (
        <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
            {children}
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

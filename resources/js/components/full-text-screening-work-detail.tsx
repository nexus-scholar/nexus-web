import { ExternalLink, FileDown } from 'lucide-react';
import { DecisionBadge } from '@/components/decision-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { FullTextScreeningSelectedAssignment } from '@/types';

type FullTextScreeningWorkDetailProps = {
    assignment: FullTextScreeningSelectedAssignment | null;
    canDownload: boolean;
};

export function FullTextScreeningWorkDetail({
    assignment,
    canDownload,
}: FullTextScreeningWorkDetailProps) {
    if (!assignment) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Work details</CardTitle>
                    <CardDescription>
                        Select a full-text assignment.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const work = assignment.work;
    const artifact = assignment.artifact;

    return (
        <Card>
            <CardHeader className="space-y-3">
                <div className="flex flex-wrap items-center gap-1.5">
                    {work.year && <Badge variant="outline">{work.year}</Badge>}
                    {work.language && (
                        <Badge variant="outline">
                            {work.language.toUpperCase()}
                        </Badge>
                    )}
                    <Badge variant="outline">
                        {work.cited_by_count.toLocaleString()} citations
                    </Badge>
                    <DecisionBadge
                        decision={assignment.title_abstract_handoff.decision}
                        label={
                            assignment.title_abstract_handoff.decision_label ??
                            undefined
                        }
                    />
                </div>
                <div className="space-y-1">
                    <CardTitle className="text-base leading-6">
                        {work.title}
                    </CardTitle>
                    <CardDescription>
                        {[work.venue_name, work.venue_type]
                            .filter(Boolean)
                            .join(' / ') || 'No venue metadata recorded'}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                <section className="rounded-md border bg-muted/20 p-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="space-y-1">
                            <h3 className="text-sm font-medium">
                                Linked artifact
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                {artifact
                                    ? [
                                          artifact.source_alias,
                                          artifact.artifact_type,
                                          artifact.status_label,
                                      ]
                                          .filter(Boolean)
                                          .join(' / ')
                                    : 'No artifact linked.'}
                            </p>
                        </div>
                        {artifact?.download_url && canDownload && (
                            <Button size="sm" variant="outline" asChild>
                                <a href={artifact.download_url}>
                                    <FileDown className="size-4" />
                                    Open artifact
                                </a>
                            </Button>
                        )}
                    </div>
                    {artifact?.artifact_path && (
                        <div className="mt-3 truncate text-xs text-muted-foreground">
                            {artifact.artifact_path}
                        </div>
                    )}
                </section>

                <section className="space-y-2">
                    <h3 className="text-sm font-medium">
                        Title and abstract handoff
                    </h3>
                    <p className="rounded-md border bg-muted/20 p-3 text-sm leading-6 text-muted-foreground">
                        {assignment.title_abstract_handoff.reason ??
                            'No handoff rationale recorded.'}
                    </p>
                </section>

                <section className="space-y-2">
                    <h3 className="text-sm font-medium">Abstract</h3>
                    <p className="max-h-72 overflow-auto rounded-md border bg-muted/20 p-3 text-sm leading-6 text-muted-foreground">
                        {work.abstract ?? 'No abstract recorded.'}
                    </p>
                </section>

                <section className="grid gap-3 lg:grid-cols-2">
                    <DetailList
                        title="Identifiers"
                        empty="No identifiers recorded."
                        items={work.identifiers.map(
                            (identifier) =>
                                `${identifier.namespace}: ${identifier.value}`,
                        )}
                    />
                    <DetailList
                        title="Providers"
                        empty="No providers recorded."
                        items={work.providers.map((provider) =>
                            provider.provider_work_id
                                ? `${provider.provider_alias}: ${provider.provider_work_id}`
                                : provider.provider_alias,
                        )}
                    />
                </section>

                <section className="space-y-2">
                    <h3 className="text-sm font-medium">Snapshot provenance</h3>
                    {work.provenance.length === 0 ? (
                        <div className="rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
                            No snapshot provenance recorded.
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {work.provenance.map((item, index) => (
                                <div
                                    key={`${item.query_label}-${index}`}
                                    className="rounded-md border bg-muted/20 p-3 text-sm"
                                >
                                    <div className="flex items-center gap-2 font-medium">
                                        <ExternalLink className="size-4 text-muted-foreground" />
                                        {item.query_label}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {[
                                            item.provider_alias,
                                            item.provider_work_id,
                                            item.rank
                                                ? `rank ${item.rank}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' / ') || 'Snapshot record'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </CardContent>
        </Card>
    );
}

function DetailList({
    empty,
    items,
    title,
}: {
    title: string;
    empty: string;
    items: string[];
}) {
    return (
        <section className="space-y-2">
            <h3 className="text-sm font-medium">{title}</h3>
            <div className="min-h-20 rounded-md border bg-muted/20 p-3 text-sm">
                {items.length === 0 ? (
                    <span className="text-muted-foreground">{empty}</span>
                ) : (
                    <div className="flex flex-wrap gap-1.5">
                        {items.map((item) => (
                            <Badge key={item} variant="outline">
                                {item}
                            </Badge>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

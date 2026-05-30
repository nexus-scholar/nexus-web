import { Download, ExternalLink, FileText, Link2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { DecisionBadge } from '@/components/decision-badge';
import { FullTextStatusBadge } from '@/components/full-text-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { FullTextItem } from '@/types';

type FullTextArtifactSheetProps = {
    item: FullTextItem | null;
    open: boolean;
    canDownload?: boolean;
    onOpenChange: (open: boolean) => void;
};

export function FullTextArtifactSheet({
    canDownload = false,
    item,
    onOpenChange,
    open,
}: FullTextArtifactSheetProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                {item ? (
                    <>
                        <SheetHeader>
                            <SheetTitle className="pr-8">
                                {item.work.title}
                            </SheetTitle>
                            <SheetDescription>
                                Full-text status, screening rationale, and
                                source audit evidence.
                            </SheetDescription>
                        </SheetHeader>

                        <div className="space-y-4 px-4 pb-4">
                            <section className="grid gap-3 sm:grid-cols-2">
                                <Fact label="Status">
                                    <FullTextStatusBadge
                                        status={item.status}
                                        label={item.status_label}
                                    />
                                </Fact>
                                <Fact label="Screening decision">
                                    <DecisionBadge
                                        decision={item.screening_decision}
                                        label={item.screening_decision_label}
                                    />
                                </Fact>
                                <Fact label="Source">
                                    {item.source_alias ?? 'No source recorded'}
                                </Fact>
                                <Fact label="HTTP status">
                                    {item.http_status ?? 'Not recorded'}
                                </Fact>
                            </section>

                            {item.screening_reason && (
                                <section className="rounded-md border bg-muted/20 p-3">
                                    <h3 className="text-sm font-medium">
                                        Screening rationale
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {item.screening_reason}
                                    </p>
                                </section>
                            )}

                            <section className="rounded-md border bg-muted/20 p-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            Artifact
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {item.artifact_path ??
                                                item.error_message ??
                                                'No artifact path recorded.'}
                                        </p>
                                    </div>
                                    <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                </div>
                                {item.download_url && canDownload && (
                                    <Button className="mt-3" size="sm" asChild>
                                        <a href={item.download_url}>
                                            <Download className="size-4" />
                                            Download artifact
                                        </a>
                                    </Button>
                                )}
                            </section>

                            <section className="rounded-md border">
                                <div className="border-b p-3">
                                    <h3 className="text-sm font-medium">
                                        Source attempts
                                    </h3>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Attempts are read from the core
                                        full-text audit trail.
                                    </p>
                                </div>
                                {item.source_attempts.length === 0 ? (
                                    <div className="p-3 text-sm text-muted-foreground">
                                        No source attempts recorded yet.
                                    </div>
                                ) : (
                                    <div className="divide-y">
                                        {item.source_attempts.map((attempt) => (
                                            <div
                                                key={attempt.id}
                                                className="space-y-2 p-3"
                                            >
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <FullTextStatusBadge
                                                        status={
                                                            attempt.status ===
                                                            'failure'
                                                                ? 'failed'
                                                                : attempt.status
                                                        }
                                                        label={
                                                            attempt.status_label
                                                        }
                                                    />
                                                    <Badge variant="outline">
                                                        {attempt.source_alias ??
                                                            'unknown source'}
                                                    </Badge>
                                                    {attempt.http_status && (
                                                        <Badge variant="outline">
                                                            HTTP{' '}
                                                            {
                                                                attempt.http_status
                                                            }
                                                        </Badge>
                                                    )}
                                                </div>
                                                {attempt.source_url && (
                                                    <a
                                                        className="inline-flex max-w-full items-center gap-1 truncate text-xs text-brand hover:underline"
                                                        href={
                                                            attempt.source_url
                                                        }
                                                        rel="noreferrer"
                                                        target="_blank"
                                                    >
                                                        <ExternalLink className="size-3" />
                                                        {attempt.source_url}
                                                    </a>
                                                )}
                                                {attempt.error_message && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {attempt.error_message}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </section>

                            <section className="rounded-md border bg-muted/20 p-3">
                                <h3 className="text-sm font-medium">
                                    Identifiers
                                </h3>
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {item.work.identifiers.length === 0 ? (
                                        <span className="text-sm text-muted-foreground">
                                            No identifiers recorded.
                                        </span>
                                    ) : (
                                        item.work.identifiers.map(
                                            (identifier) => (
                                                <Badge
                                                    key={`${identifier.namespace}:${identifier.value}`}
                                                    variant="outline"
                                                >
                                                    <Link2 className="size-3" />
                                                    {identifier.namespace}:{' '}
                                                    {identifier.value}
                                                </Badge>
                                            ),
                                        )
                                    )}
                                </div>
                            </section>
                        </div>
                    </>
                ) : (
                    <>
                        <SheetHeader>
                            <SheetTitle>No record selected</SheetTitle>
                            <SheetDescription>
                                Open a full-text candidate to inspect source
                                attempts and artifact metadata.
                            </SheetDescription>
                        </SheetHeader>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}

function Fact({ children, label }: { children: ReactNode; label: string }) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{children}</div>
        </div>
    );
}

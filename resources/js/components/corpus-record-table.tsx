import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { MetadataCompletenessBadge } from '@/components/metadata-completeness-badge';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { CorpusRecord } from '@/types';

type CorpusRecordTableProps = {
    records: CorpusRecord[];
    selectedWorkId: string | null;
    recordHref: (workId: string) => string;
};

export function CorpusRecordTable({
    recordHref,
    records,
    selectedWorkId,
}: CorpusRecordTableProps) {
    if (records.length === 0) {
        return (
            <div className="rounded-lg border bg-card p-8 text-center shadow-xs">
                <div className="text-sm font-medium">No corpus records</div>
                <p className="mt-1 text-sm text-muted-foreground">
                    Run search first or adjust the current filters.
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-lg border bg-card shadow-xs">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="min-w-[24rem]">Record</TableHead>
                        <TableHead>Year</TableHead>
                        <TableHead>Providers</TableHead>
                        <TableHead>Quality</TableHead>
                        <TableHead className="text-right">Links</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {records.map((record) => (
                        <TableRow
                            key={record.id}
                            className={cn(
                                selectedWorkId === record.id &&
                                    'bg-brand-muted/60 hover:bg-brand-muted/60',
                            )}
                        >
                            <TableCell className="whitespace-normal">
                                <Link
                                    href={recordHref(record.id)}
                                    preserveScroll
                                    className="group block min-w-0"
                                >
                                    <span className="line-clamp-2 text-sm font-medium text-foreground group-hover:underline">
                                        {record.title}
                                    </span>
                                    <span className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        {record.venue_name && (
                                            <span>{record.venue_name}</span>
                                        )}
                                        {record.url && (
                                            <ExternalLink className="size-3" />
                                        )}
                                    </span>
                                </Link>
                            </TableCell>
                            <TableCell>{record.year ?? 'Any'}</TableCell>
                            <TableCell>
                                <div className="flex max-w-56 flex-wrap gap-1">
                                    {record.providers.length === 0 ? (
                                        <span className="text-xs text-muted-foreground">
                                            None
                                        </span>
                                    ) : (
                                        record.providers.map((provider) => (
                                            <Badge
                                                key={provider.provider_alias}
                                                variant="outline"
                                            >
                                                {provider.provider_alias}
                                            </Badge>
                                        ))
                                    )}
                                </div>
                            </TableCell>
                            <TableCell>
                                <MetadataCompletenessBadge
                                    flags={record.metadata_flags}
                                    label={record.quality_label}
                                />
                            </TableCell>
                            <TableCell className="text-right">
                                {record.counts.provenance}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

import { Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Columns3,
    Copy,
    Eye,
    MoreHorizontal,
    RotateCcw,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { MetadataCompletenessBadge } from '@/components/metadata-completeness-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type {
    CorpusFilters,
    CorpusRecord,
    CorpusSortDirection,
    CorpusSortKey,
} from '@/types';

type CorpusRecordTableProps = {
    records: CorpusRecord[];
    selectedWorkId: string | null;
    recordHref: (workId: string) => string;
    sort: CorpusSortKey;
    direction: CorpusSortDirection;
    onSort: (filters: Partial<CorpusFilters>) => void;
};

type ColumnKey =
    | 'record'
    | 'year'
    | 'providers'
    | 'quality'
    | 'citations'
    | 'retrieved'
    | 'links';

const columnLabels: Record<ColumnKey, string> = {
    citations: 'Citations',
    links: 'Links',
    providers: 'Providers',
    quality: 'Quality',
    record: 'Record',
    retrieved: 'Retrieved',
    year: 'Year',
};

const defaultVisibleColumns = new Set<ColumnKey>([
    'record',
    'year',
    'providers',
    'quality',
    'citations',
    'links',
]);

export function CorpusRecordTable({
    direction,
    onSort,
    recordHref,
    records,
    selectedWorkId,
    sort,
}: CorpusRecordTableProps) {
    const [visibleColumns, setVisibleColumns] = useState(defaultVisibleColumns);
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const visibleIds = useMemo(
        () => records.map((record) => record.id),
        [records],
    );
    const selectedVisibleIds = visibleIds.filter((id) => selectedIds.has(id));
    const selectedVisibleCount = selectedVisibleIds.length;
    const allVisibleSelected =
        visibleIds.length > 0 && selectedVisibleCount === visibleIds.length;
    const someVisibleSelected = selectedVisibleCount > 0 && !allVisibleSelected;

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

    const toggleColumn = (column: ColumnKey, checked: boolean) => {
        setVisibleColumns((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(column);
            } else {
                next.delete(column);
            }

            next.add('record');

            return next;
        });
    };

    const toggleAllVisible = (checked: boolean) => {
        setSelectedIds((current) => {
            const next = new Set(current);

            visibleIds.forEach((id) => {
                if (checked) {
                    next.add(id);
                } else {
                    next.delete(id);
                }
            });

            return next;
        });
    };

    const toggleRow = (id: string, checked: boolean) => {
        setSelectedIds((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });
    };

    const copySelectedIds = async () => {
        await navigator.clipboard?.writeText(selectedVisibleIds.join('\n'));
    };

    return (
        <div className="rounded-lg border bg-card shadow-xs">
            <div className="flex flex-col gap-3 border-b p-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="font-medium">{records.length} rows</span>
                    <span className="text-muted-foreground">
                        {selectedVisibleCount} selected
                    </span>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <MoreHorizontal className="size-4" />
                                Bulk actions
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel>
                                {selectedVisibleCount} selected
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                disabled={selectedVisibleCount === 0}
                                onClick={copySelectedIds}
                            >
                                <Copy className="size-4" />
                                Copy work IDs
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                disabled={selectedVisibleCount === 0}
                                onClick={() => {
                                    setSelectedIds(new Set());
                                }}
                            >
                                <RotateCcw className="size-4" />
                                Clear selection
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Columns3 className="size-4" />
                                Columns
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel>
                                Visible columns
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {Object.entries(columnLabels).map(
                                ([key, label]) => (
                                    <DropdownMenuCheckboxItem
                                        key={key}
                                        checked={visibleColumns.has(
                                            key as ColumnKey,
                                        )}
                                        disabled={key === 'record'}
                                        onCheckedChange={(checked) => {
                                            toggleColumn(
                                                key as ColumnKey,
                                                checked === true,
                                            );
                                        }}
                                    >
                                        {label}
                                    </DropdownMenuCheckboxItem>
                                ),
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <Table className="min-w-[76rem]">
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-10">
                            <Checkbox
                                aria-label="Select all records on this page"
                                checked={
                                    allVisibleSelected ||
                                    (someVisibleSelected && 'indeterminate')
                                }
                                onCheckedChange={(checked) => {
                                    toggleAllVisible(checked === true);
                                }}
                            />
                        </TableHead>
                        {visibleColumns.has('record') && (
                            <TableHead className="min-w-[28rem]">
                                <SortableHeader
                                    column="title"
                                    direction={direction}
                                    label="Record"
                                    onSort={onSort}
                                    sort={sort}
                                />
                            </TableHead>
                        )}
                        {visibleColumns.has('year') && (
                            <TableHead className="w-28">
                                <SortableHeader
                                    column="year"
                                    direction={direction}
                                    label="Year"
                                    onSort={onSort}
                                    sort={sort}
                                />
                            </TableHead>
                        )}
                        {visibleColumns.has('providers') && (
                            <TableHead className="min-w-52">
                                Providers
                            </TableHead>
                        )}
                        {visibleColumns.has('quality') && (
                            <TableHead className="min-w-44">Quality</TableHead>
                        )}
                        {visibleColumns.has('citations') && (
                            <TableHead className="w-32">
                                <SortableHeader
                                    column="cited_by_count"
                                    direction={direction}
                                    label="Citations"
                                    onSort={onSort}
                                    sort={sort}
                                />
                            </TableHead>
                        )}
                        {visibleColumns.has('retrieved') && (
                            <TableHead className="w-40">
                                <SortableHeader
                                    column="retrieved_at"
                                    direction={direction}
                                    label="Retrieved"
                                    onSort={onSort}
                                    sort={sort}
                                />
                            </TableHead>
                        )}
                        {visibleColumns.has('links') && (
                            <TableHead className="w-28 text-right">
                                Links
                            </TableHead>
                        )}
                        <TableHead className="sticky right-0 z-10 w-28 bg-card text-right shadow-[-10px_0_14px_-14px_rgb(15_23_42/0.45)]">
                            Detail
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {records.map((record) => (
                        <TableRow
                            key={record.id}
                            data-state={
                                selectedWorkId === record.id
                                    ? 'selected'
                                    : undefined
                            }
                        >
                            <TableCell>
                                <Checkbox
                                    aria-label={`Select ${record.title}`}
                                    checked={selectedIds.has(record.id)}
                                    onCheckedChange={(checked) => {
                                        toggleRow(record.id, checked === true);
                                    }}
                                />
                            </TableCell>
                            {visibleColumns.has('record') && (
                                <TableCell className="whitespace-normal">
                                    <div className="min-w-0">
                                        <div className="line-clamp-2 text-sm font-medium text-foreground">
                                            {record.title}
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                            {record.venue_name && (
                                                <span>{record.venue_name}</span>
                                            )}
                                            {record.language && (
                                                <span className="uppercase">
                                                    {record.language}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </TableCell>
                            )}
                            {visibleColumns.has('year') && (
                                <TableCell>{record.year ?? 'Any'}</TableCell>
                            )}
                            {visibleColumns.has('providers') && (
                                <TableCell>
                                    <div className="flex max-w-60 flex-wrap gap-1">
                                        {record.providers.length === 0 ? (
                                            <span className="text-xs text-muted-foreground">
                                                None
                                            </span>
                                        ) : (
                                            record.providers.map((provider) => (
                                                <Badge
                                                    key={
                                                        provider.provider_alias
                                                    }
                                                    variant="outline"
                                                >
                                                    {provider.provider_alias}
                                                </Badge>
                                            ))
                                        )}
                                    </div>
                                </TableCell>
                            )}
                            {visibleColumns.has('quality') && (
                                <TableCell>
                                    <MetadataCompletenessBadge
                                        flags={record.metadata_flags}
                                        label={record.quality_label}
                                    />
                                </TableCell>
                            )}
                            {visibleColumns.has('citations') && (
                                <TableCell>
                                    {record.cited_by_count.toLocaleString()}
                                </TableCell>
                            )}
                            {visibleColumns.has('retrieved') && (
                                <TableCell>
                                    {formatDate(record.retrieved_at)}
                                </TableCell>
                            )}
                            {visibleColumns.has('links') && (
                                <TableCell className="text-right">
                                    {record.counts.provenance}
                                </TableCell>
                            )}
                            <TableCell className="sticky right-0 z-10 bg-card text-right shadow-[-10px_0_14px_-14px_rgb(15_23_42/0.35)]">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    asChild
                                    className={cn(
                                        selectedWorkId === record.id &&
                                            'bg-brand-muted text-brand-muted-foreground',
                                    )}
                                >
                                    <Link
                                        href={recordHref(record.id)}
                                        preserveState
                                        preserveScroll
                                    >
                                        <Eye className="size-4" />
                                        View
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function SortableHeader({
    column,
    direction,
    label,
    onSort,
    sort,
}: {
    column: CorpusSortKey;
    direction: CorpusSortDirection;
    label: string;
    sort: CorpusSortKey;
    onSort: (filters: Partial<CorpusFilters>) => void;
}) {
    const active = sort === column;
    const nextDirection: CorpusSortDirection = active
        ? direction === 'asc'
            ? 'desc'
            : 'asc'
        : column === 'title'
          ? 'asc'
          : 'desc';
    const SortIcon = !active
        ? ArrowUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            className="-ml-2 h-8 px-2"
            onClick={() => {
                onSort({
                    direction: nextDirection,
                    sort: column,
                    work: null,
                });
            }}
        >
            {label}
            <SortIcon
                className={cn(
                    'size-3.5',
                    active ? 'text-foreground' : 'text-muted-foreground',
                )}
            />
        </Button>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

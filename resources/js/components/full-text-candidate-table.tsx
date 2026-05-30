import { Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Columns3,
    Download,
    Eye,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { DecisionBadge } from '@/components/decision-badge';
import { FullTextStatusBadge } from '@/components/full-text-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { FullTextItem } from '@/types';

type FullTextCandidateTableProps = {
    items: FullTextItem[];
    selectedItemId: string | null;
    itemHref: (id: string) => string;
    canDownload?: boolean;
};

type SortKey = 'title' | 'year' | 'status';
type SortDirection = 'asc' | 'desc';
type OptionalColumn = 'year' | 'decision' | 'providers' | 'artifact';

const optionalColumns: Array<{
    key: OptionalColumn;
    label: string;
}> = [
    { key: 'decision', label: 'Decision' },
    { key: 'year', label: 'Year' },
    { key: 'providers', label: 'Providers' },
    { key: 'artifact', label: 'Artifact' },
];

export function FullTextCandidateTable({
    canDownload = false,
    itemHref,
    items,
    selectedItemId,
}: FullTextCandidateTableProps) {
    const [query, setQuery] = useState('');
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const [visibleColumns, setVisibleColumns] = useState<
        Record<OptionalColumn, boolean>
    >({
        artifact: true,
        decision: true,
        providers: true,
        year: true,
    });
    const [sort, setSort] = useState<{
        key: SortKey;
        direction: SortDirection;
    }>({
        direction: 'asc',
        key: 'title',
    });

    const visibleItems = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();
        const filtered =
            normalizedQuery === ''
                ? items
                : items.filter((item) =>
                      [
                          item.work.title,
                          item.work.abstract,
                          item.work.venue_name,
                          item.source_alias,
                          item.status_label,
                          item.screening_decision_label,
                          ...item.work.providers.map(
                              (provider) => provider.provider_alias,
                          ),
                      ]
                          .filter(Boolean)
                          .join(' ')
                          .toLowerCase()
                          .includes(normalizedQuery),
                  );

        return [...filtered].sort((first, second) => {
            const direction = sort.direction === 'asc' ? 1 : -1;
            const firstValue = sortValue(first, sort.key);
            const secondValue = sortValue(second, sort.key);

            return firstValue.localeCompare(secondValue) * direction;
        });
    }, [items, query, sort]);

    const allVisibleSelected =
        visibleItems.length > 0 &&
        visibleItems.every((item) => selectedIds.has(item.id));

    const toggleAllVisible = (checked: boolean) => {
        setSelectedIds((current) => {
            const next = new Set(current);

            for (const item of visibleItems) {
                if (checked) {
                    next.add(item.id);
                } else {
                    next.delete(item.id);
                }
            }

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

    return (
        <div className="rounded-lg border bg-card text-card-foreground shadow-xs">
            <div className="flex flex-col gap-3 border-b p-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="relative min-w-0 flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Search full-text candidates"
                        className="pl-9"
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search title, abstract, provider, or status"
                        value={query}
                    />
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        {visibleItems.length} rows / {selectedIds.size} selected
                    </span>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Columns3 className="size-4" />
                                Columns
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            {optionalColumns.map((column) => (
                                <DropdownMenuCheckboxItem
                                    key={column.key}
                                    checked={visibleColumns[column.key]}
                                    onCheckedChange={(checked) =>
                                        setVisibleColumns((current) => ({
                                            ...current,
                                            [column.key]: Boolean(checked),
                                        }))
                                    }
                                >
                                    {column.label}
                                </DropdownMenuCheckboxItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-10">
                            <Checkbox
                                aria-label="Select visible full-text candidates"
                                checked={allVisibleSelected}
                                onCheckedChange={(checked) =>
                                    toggleAllVisible(Boolean(checked))
                                }
                            />
                        </TableHead>
                        <TableHead className="min-w-[28rem]">
                            <SortButton
                                active={sort.key === 'title'}
                                direction={sort.direction}
                                label="Record"
                                onClick={() => setSort(nextSort(sort, 'title'))}
                            />
                        </TableHead>
                        {visibleColumns.year && (
                            <TableHead className="w-24">
                                <SortButton
                                    active={sort.key === 'year'}
                                    direction={sort.direction}
                                    label="Year"
                                    onClick={() =>
                                        setSort(nextSort(sort, 'year'))
                                    }
                                />
                            </TableHead>
                        )}
                        {visibleColumns.decision && (
                            <TableHead className="w-32">Decision</TableHead>
                        )}
                        {visibleColumns.providers && (
                            <TableHead className="min-w-40">
                                Providers
                            </TableHead>
                        )}
                        <TableHead className="w-36">
                            <SortButton
                                active={sort.key === 'status'}
                                direction={sort.direction}
                                label="Status"
                                onClick={() =>
                                    setSort(nextSort(sort, 'status'))
                                }
                            />
                        </TableHead>
                        {visibleColumns.artifact && (
                            <TableHead className="min-w-40">Artifact</TableHead>
                        )}
                        <TableHead className="w-28 text-right">
                            Detail
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {visibleItems.length === 0 ? (
                        <TableRow>
                            <TableCell
                                className="h-24 text-center text-muted-foreground"
                                colSpan={8}
                            >
                                No full-text candidates match the current
                                search.
                            </TableCell>
                        </TableRow>
                    ) : (
                        visibleItems.map((item) => (
                            <TableRow
                                key={item.id}
                                data-state={
                                    item.id === selectedItemId
                                        ? 'selected'
                                        : undefined
                                }
                            >
                                <TableCell>
                                    <Checkbox
                                        aria-label={`Select ${item.work.title}`}
                                        checked={selectedIds.has(item.id)}
                                        onCheckedChange={(checked) =>
                                            toggleRow(item.id, Boolean(checked))
                                        }
                                    />
                                </TableCell>
                                <TableCell className="min-w-[28rem] whitespace-normal">
                                    <div className="line-clamp-2 font-medium">
                                        {item.work.title}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {[
                                            item.work.venue_name,
                                            item.work.language,
                                        ]
                                            .filter(Boolean)
                                            .join(' / ') || 'No venue metadata'}
                                    </div>
                                </TableCell>
                                {visibleColumns.year && (
                                    <TableCell>
                                        {item.work.year ?? 'Unknown'}
                                    </TableCell>
                                )}
                                {visibleColumns.decision && (
                                    <TableCell>
                                        <DecisionBadge
                                            decision={item.screening_decision}
                                            label={
                                                item.screening_decision_label
                                            }
                                        />
                                    </TableCell>
                                )}
                                {visibleColumns.providers && (
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {item.work.providers.length ===
                                            0 ? (
                                                <span className="text-xs text-muted-foreground">
                                                    None
                                                </span>
                                            ) : (
                                                item.work.providers
                                                    .slice(0, 3)
                                                    .map((provider) => (
                                                        <Badge
                                                            key={`${item.id}:${provider.provider_alias}:${provider.provider_work_id}`}
                                                            variant="outline"
                                                        >
                                                            {
                                                                provider.provider_alias
                                                            }
                                                        </Badge>
                                                    ))
                                            )}
                                        </div>
                                    </TableCell>
                                )}
                                <TableCell>
                                    <FullTextStatusBadge
                                        status={item.status}
                                        label={item.status_label}
                                    />
                                </TableCell>
                                {visibleColumns.artifact && (
                                    <TableCell>
                                        <ArtifactCell
                                            canDownload={canDownload}
                                            item={item}
                                        />
                                    </TableCell>
                                )}
                                <TableCell className="text-right">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={itemHref(item.id)}>
                                            <Eye className="size-4" />
                                            View
                                        </Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

function SortButton({
    active,
    direction,
    label,
    onClick,
}: {
    active: boolean;
    direction: SortDirection;
    label: string;
    onClick: () => void;
}) {
    const Icon = active
        ? direction === 'asc'
            ? ArrowUp
            : ArrowDown
        : ArrowUpDown;

    return (
        <button
            className={cn(
                'inline-flex items-center gap-1 rounded-sm text-left hover:text-foreground',
                active ? 'text-foreground' : 'text-muted-foreground',
            )}
            onClick={onClick}
            type="button"
        >
            {label}
            <Icon className="size-3.5" />
        </button>
    );
}

function ArtifactCell({
    canDownload,
    item,
}: {
    canDownload: boolean;
    item: FullTextItem;
}) {
    if (item.download_url && canDownload) {
        return (
            <Button variant="outline" size="sm" asChild>
                <a href={item.download_url}>
                    <Download className="size-4" />
                    Download
                </a>
            </Button>
        );
    }

    if (item.artifact_type) {
        return <span className="text-sm">{item.artifact_type}</span>;
    }

    return <span className="text-sm text-muted-foreground">No artifact</span>;
}

function nextSort(
    current: {
        key: SortKey;
        direction: SortDirection;
    },
    key: SortKey,
): {
    key: SortKey;
    direction: SortDirection;
} {
    if (current.key !== key) {
        return { direction: 'asc', key };
    }

    return {
        direction: current.direction === 'asc' ? 'desc' : 'asc',
        key,
    };
}

function sortValue(item: FullTextItem, key: SortKey): string {
    if (key === 'year') {
        return String(item.work.year ?? 0).padStart(4, '0');
    }

    if (key === 'status') {
        return item.status;
    }

    return item.work.title.toLowerCase();
}

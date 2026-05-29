import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { CorpusSource } from '@/types';

type CorpusStatusBadgeProps = {
    source: CorpusSource;
    className?: string;
};

const sourceMeta = {
    draft: {
        label: 'Query-linked draft',
        className:
            'border-transparent bg-brand-muted text-brand-muted-foreground',
    },
    locked: {
        label: 'Snapshot locked',
        className: 'border-transparent bg-status-audit-bg text-status-audit',
    },
} satisfies Record<CorpusSource, { label: string; className: string }>;

export function CorpusStatusBadge({
    className,
    source,
}: CorpusStatusBadgeProps) {
    const meta = sourceMeta[source];

    return (
        <Badge
            variant="outline"
            className={cn('whitespace-nowrap', meta.className, className)}
        >
            {meta.label}
        </Badge>
    );
}

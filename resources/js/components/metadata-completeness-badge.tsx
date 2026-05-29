import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { CorpusMetadataFlags } from '@/types';

type MetadataCompletenessBadgeProps = {
    flags: CorpusMetadataFlags;
    label?: string;
    className?: string;
};

export function MetadataCompletenessBadge({
    className,
    flags,
    label,
}: MetadataCompletenessBadgeProps) {
    const meta = metadataMeta(flags);

    return (
        <Badge
            variant="outline"
            className={cn('whitespace-nowrap', meta.className, className)}
        >
            {label ?? meta.label}
        </Badge>
    );
}

function metadataMeta(flags: CorpusMetadataFlags): {
    label: string;
    className: string;
} {
    if (flags.retracted) {
        return {
            label: 'Retracted',
            className:
                'border-transparent bg-status-exclude-bg text-status-exclude',
        };
    }

    if (flags.missing_abstract && flags.missing_identifier) {
        return {
            label: 'Needs metadata',
            className:
                'border-transparent bg-status-conflict-bg text-status-conflict',
        };
    }

    if (flags.missing_abstract) {
        return {
            label: 'Missing abstract',
            className:
                'border-transparent bg-status-pending-bg text-status-pending',
        };
    }

    if (flags.missing_identifier) {
        return {
            label: 'Missing identifier',
            className:
                'border-transparent bg-status-pending-bg text-status-pending',
        };
    }

    if (flags.in_duplicate_cluster) {
        return {
            label: 'Duplicate candidate',
            className:
                'border-transparent bg-status-audit-bg text-status-audit',
        };
    }

    return {
        label: 'Ready',
        className:
            'border-transparent bg-status-include-bg text-status-include',
    };
}

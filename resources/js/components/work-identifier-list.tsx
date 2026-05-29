import { Badge } from '@/components/ui/badge';
import type { CorpusIdentifier } from '@/types';

type WorkIdentifierListProps = {
    identifiers: CorpusIdentifier[];
};

export function WorkIdentifierList({ identifiers }: WorkIdentifierListProps) {
    if (identifiers.length === 0) {
        return (
            <div className="rounded-md border bg-status-pending-bg p-3 text-sm text-status-pending">
                No identifier recorded.
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            {identifiers.map((identifier) => (
                <Badge
                    key={`${identifier.namespace}-${identifier.value}`}
                    variant="outline"
                    className="max-w-full gap-1"
                >
                    <span>{identifier.namespace.toUpperCase()}</span>
                    <span className="max-w-64 truncate font-mono">
                        {identifier.value}
                    </span>
                    {identifier.is_primary && (
                        <span className="text-muted-foreground">primary</span>
                    )}
                </Badge>
            ))}
        </div>
    );
}

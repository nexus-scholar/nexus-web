import { AlertCircle, CheckCircle2, CircleDashed } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ProtocolReadinessItem } from '@/types';

type ProtocolReadinessListProps = {
    items: ProtocolReadinessItem[];
    title?: string;
    description?: string;
    emptyState?: ReactNode;
    className?: string;
};

export function ProtocolReadinessList({
    className,
    description = 'Required protocol fields before search can start.',
    emptyState = 'No protocol fields have been configured yet.',
    items,
    title = 'Protocol readiness',
}: ProtocolReadinessListProps) {
    const requiredItems = items.filter((item) => item.required !== false);
    const completedRequiredItems = requiredItems.filter(
        (item) => item.complete,
    );
    const missingRequiredCount =
        requiredItems.length - completedRequiredItems.length;
    const isReady = missingRequiredCount === 0 && requiredItems.length > 0;

    return (
        <section
            aria-label={title}
            className={cn(
                'rounded-lg border bg-card text-card-foreground shadow-xs',
                className,
            )}
        >
            <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <h2 className="text-base leading-6 font-medium">{title}</h2>
                    <p className="text-sm leading-5 text-muted-foreground">
                        {description}
                    </p>
                </div>
                <Badge
                    variant="outline"
                    className={cn(
                        'w-fit shrink-0',
                        isReady
                            ? 'border-transparent bg-status-include-bg text-status-include'
                            : 'border-transparent bg-status-pending-bg text-status-pending',
                    )}
                >
                    {completedRequiredItems.length}/{requiredItems.length}{' '}
                    required
                </Badge>
            </div>

            {items.length === 0 ? (
                <div className="p-4 text-sm text-muted-foreground">
                    {emptyState}
                </div>
            ) : (
                <div className="divide-y">
                    {items.map((item) => (
                        <ReadinessItem key={item.id} item={item} />
                    ))}
                </div>
            )}
        </section>
    );
}

function ReadinessItem({ item }: { item: ProtocolReadinessItem }) {
    const isRequired = item.required !== false;
    const Icon = item.complete
        ? CheckCircle2
        : isRequired
          ? AlertCircle
          : CircleDashed;

    return (
        <div className="grid gap-3 p-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-start">
            <Icon
                className={cn(
                    'mt-0.5 size-4',
                    item.complete && 'text-status-include',
                    !item.complete && isRequired && 'text-status-audit',
                    !item.complete && !isRequired && 'text-muted-foreground',
                )}
                aria-hidden="true"
            />

            <div className="min-w-0 space-y-1">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <h3 className="text-sm leading-5 font-medium">
                        {item.label}
                    </h3>
                    {isRequired && !item.complete && (
                        <Badge variant="outline" className="text-xs">
                            Required
                        </Badge>
                    )}
                </div>
                {item.description && (
                    <p className="text-sm leading-5 text-muted-foreground">
                        {item.description}
                    </p>
                )}
                {item.value && (
                    <div className="text-sm leading-5 text-muted-foreground">
                        {item.value}
                    </div>
                )}
            </div>

            {item.action && <div className="shrink-0">{item.action}</div>}
        </div>
    );
}

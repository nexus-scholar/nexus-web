import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type MetricCardProps = {
    label: string;
    value: string | number;
    description?: string;
    icon?: LucideIcon;
    className?: string;
};

export function MetricCard({
    className,
    description,
    icon: Icon,
    label,
    value,
}: MetricCardProps) {
    return (
        <div
            className={cn(
                'rounded-lg border bg-card p-4 text-card-foreground shadow-xs',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <div className="mt-2 text-2xl font-semibold tabular-nums">
                        {value}
                    </div>
                </div>
                {Icon && (
                    <div className="flex size-8 items-center justify-center rounded-md bg-muted text-muted-foreground">
                        <Icon className="size-4" />
                    </div>
                )}
            </div>
            {description && (
                <p className="mt-3 text-xs leading-5 text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}

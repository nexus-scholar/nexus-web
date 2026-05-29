import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PageShellProps = {
    children: ReactNode;
    className?: string;
};

type PageHeaderProps = {
    title: string;
    description?: string;
    eyebrow?: string;
    actions?: ReactNode;
    className?: string;
};

export function PageShell({ children, className }: PageShellProps) {
    return (
        <div
            className={cn(
                'flex flex-1 flex-col gap-4 p-4 md:gap-5 md:p-5',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function PageHeader({
    actions,
    className,
    description,
    eyebrow,
    title,
}: PageHeaderProps) {
    return (
        <header
            className={cn(
                'flex flex-col gap-3 border-b border-border/70 pb-4 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0 space-y-1">
                {eyebrow && (
                    <p className="text-xs font-medium tracking-normal text-muted-foreground">
                        {eyebrow}
                    </p>
                )}
                <h1 className="truncate text-xl leading-7 font-semibold">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-2xl text-sm leading-5 text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}

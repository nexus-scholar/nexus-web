import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type SettingsSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
    tone?: 'default' | 'danger';
};

export function SettingsSection({
    children,
    className,
    description,
    title,
    tone = 'default',
}: SettingsSectionProps) {
    return (
        <Card
            className={cn(
                tone === 'danger' &&
                    'border-destructive/25 bg-status-exclude-bg/35 dark:bg-status-exclude-bg/15',
                className,
            )}
        >
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Clock3 } from 'lucide-react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type ActivityEvent = {
    id: string;
    event_type: string;
    reason: string | null;
    occurred_at: string;
    actor: {
        id: number;
        name: string;
        email: string;
    } | null;
};

type Props = {
    project: {
        id: string;
        name: string;
        status: string;
        urls: {
            overview: string;
            protocol: string;
            activity: string;
        };
    };
    events: ActivityEvent[];
};

export default function ProjectActivity({ events, project }: Props) {
    return (
        <>
            <Head title={`${project.name} activity`} />

            <PageShell>
                <PageHeader
                    eyebrow="Project activity"
                    title={project.name}
                    description="Audited project and protocol events."
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={project.urls.overview}>Overview</Link>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Audit trail</CardTitle>
                        <CardDescription>
                            Latest project-scoped audit events.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {events.length === 0 ? (
                            <div className="py-6 text-sm text-muted-foreground">
                                No project activity has been recorded yet.
                            </div>
                        ) : (
                            events.map((event) => (
                                <div
                                    key={event.id}
                                    className="grid gap-2 py-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-start"
                                >
                                    <Clock3 className="mt-0.5 size-4 text-muted-foreground" />
                                    <div className="min-w-0">
                                        <div className="text-sm font-medium">
                                            {event.event_type}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {event.actor?.name ?? 'System'}
                                        </div>
                                        {event.reason && (
                                            <p className="mt-2 text-sm leading-5 text-muted-foreground">
                                                {event.reason}
                                            </p>
                                        )}
                                    </div>
                                    <time className="text-xs text-muted-foreground">
                                        {event.occurred_at}
                                    </time>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

ProjectActivity.layout = {
    breadcrumbs: [
        {
            title: 'Project activity',
            href: '#',
        },
    ],
};

import { Head, Link, useForm } from '@inertiajs/react';
import { SearchCheck } from 'lucide-react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectSearchPlanForm } from '@/components/project-search-plan-form';
import type { ProjectSearchPlanFormData } from '@/components/project-search-plan-form';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { Button } from '@/components/ui/button';
import type { ProjectStatus, ReviewType, SearchPlanStatus } from '@/types';

type ProjectPayload = {
    id: string;
    name: string;
    review_type: ReviewType;
    review_type_label: string;
    status: ProjectStatus;
    status_label: string;
    locked_at: string | null;
    workspace: {
        id: string;
        name: string;
    };
    urls: {
        overview: string;
        protocol: string;
        search_plan: string;
        activity: string;
    };
    protocol: {
        id: string;
        status: string;
        status_label: string;
        version: number;
        completed_at: string | null;
    };
};

type SearchPlanPayload = ProjectSearchPlanFormData & {
    id: string;
    status: SearchPlanStatus;
    status_label: string;
    version: number;
};

type Props = {
    project: ProjectPayload;
    searchPlan: SearchPlanPayload;
    can: {
        update_search_plan: boolean;
        run_search: boolean;
    };
};

export default function ProjectSearchPlan({ can, project, searchPlan }: Props) {
    const form = useForm<ProjectSearchPlanFormData>({
        default_providers: searchPlan.default_providers,
        default_year_from: searchPlan.default_year_from,
        default_year_to: searchPlan.default_year_to,
        default_result_limit: searchPlan.default_result_limit,
        include_raw_data: searchPlan.include_raw_data,
        queries: searchPlan.queries,
    });
    const formErrors = form.errors as Record<string, string | undefined>;

    const submit = () => {
        form.patch(project.urls.search_plan, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`${project.name} search plan`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / Protocol v${project.protocol.version}`}
                    title="Search plan"
                    description="Draft provider-ready queries before background search execution."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.protocol}>
                                    Protocol
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.overview}>
                                    Overview
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="rounded-lg border bg-brand-muted p-4 text-brand-muted-foreground shadow-xs">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-background/70">
                            <SearchCheck className="size-4" />
                        </div>
                        <div className="min-w-0">
                            <div className="text-sm font-medium">
                                Protocol defaults are loaded from the completed
                                protocol.
                            </div>
                            <div className="mt-1 text-sm leading-5 opacity-85">
                                Edit provider-specific query rows here. Search
                                execution will use this plan in the next
                                background workflow slice.
                            </div>
                        </div>
                    </div>
                </div>

                <ProjectSearchPlanForm
                    canRunSearch={can.run_search}
                    canUpdateSearchPlan={can.update_search_plan}
                    form={form}
                    formErrors={formErrors}
                    isLocked={Boolean(project.locked_at)}
                    planStatusLabel={searchPlan.status_label}
                    planVersion={searchPlan.version}
                    onSubmit={submit}
                />
            </PageShell>
        </>
    );
}

ProjectSearchPlan.layout = {
    breadcrumbs: [
        {
            title: 'Search plan',
            href: '#',
        },
    ],
};

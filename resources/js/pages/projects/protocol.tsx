import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectProtocolForm } from '@/components/project-protocol-form';
import type {
    ProjectProtocolFormData,
    ReviewTypeOption,
} from '@/components/project-protocol-form';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { Button } from '@/components/ui/button';
import type { ProjectStatus, ReviewType } from '@/types';

type ProjectPayload = {
    id: string;
    name: string;
    review_type: ReviewType;
    review_type_label: string;
    status: ProjectStatus;
    locked_at: string | null;
    workspace: {
        id: string;
        name: string;
    };
    urls: {
        overview: string;
        protocol: string;
        activity: string;
    };
};

type ProtocolPayload = {
    id: string;
    status: string;
    version: number;
    title: string;
    research_question: string;
    background: string;
    inclusion_criteria: string;
    exclusion_criteria: string;
    target_providers: string;
    date_range_start: string;
    date_range_end: string;
    no_date_limit: boolean;
    language_policy: string;
    min_reviewer_count: number;
    ai_screening_policy: ProjectProtocolFormData['ai_screening_policy'];
    full_text_policy: ProjectProtocolFormData['full_text_policy'];
};

type Props = {
    project: ProjectPayload;
    protocol: ProtocolPayload;
    reviewTypes: ReviewTypeOption[];
    can: {
        update_protocol: boolean;
        complete_protocol: boolean;
    };
};

export default function ProjectProtocol({
    can,
    project,
    protocol,
    reviewTypes,
}: Props) {
    const form = useForm<ProjectProtocolFormData>({
        intent: 'save',
        title: protocol.title,
        review_type: project.review_type,
        research_question: protocol.research_question,
        background: protocol.background,
        inclusion_criteria: protocol.inclusion_criteria,
        exclusion_criteria: protocol.exclusion_criteria,
        target_providers: protocol.target_providers,
        date_range_start: protocol.date_range_start,
        date_range_end: protocol.date_range_end,
        no_date_limit: protocol.no_date_limit,
        language_policy: protocol.language_policy,
        min_reviewer_count: protocol.min_reviewer_count,
        ai_screening_policy: protocol.ai_screening_policy,
        full_text_policy: protocol.full_text_policy,
        audit_reason: '',
    });
    const formErrors = form.errors as Record<string, string | undefined>;

    const submit = (intent: ProjectProtocolFormData['intent']) => {
        form.transform((data) => ({
            ...data,
            intent,
        }));
        form.patch(project.urls.protocol, {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <>
            <Head title={`${project.name} protocol`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / Protocol v${protocol.version}`}
                    title="Protocol"
                    description="Define the review question, eligibility criteria, provider plan, and audit policies."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.overview}>
                                    Overview
                                </Link>
                            </Button>
                        </>
                    }
                />

                <ProjectProtocolForm
                    canCompleteProtocol={can.complete_protocol}
                    canUpdateProtocol={can.update_protocol}
                    form={form}
                    formErrors={formErrors}
                    isLocked={Boolean(project.locked_at)}
                    reviewTypes={reviewTypes}
                    onSubmitIntent={submit}
                />
            </PageShell>
        </>
    );
}

ProjectProtocol.layout = {
    breadcrumbs: [
        {
            title: 'Project protocol',
            href: '#',
        },
    ],
};

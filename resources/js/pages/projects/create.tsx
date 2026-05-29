import { Head, Link, useForm } from '@inertiajs/react';
import { FileText, FolderPlus, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { WorkflowStepCard } from '@/components/workflow-step-card';
import { dashboard } from '@/routes';
import type { ReviewType } from '@/types';

type ReviewTypeOption = {
    value: ReviewType;
    label: string;
};

type Props = {
    selectedWorkspace: {
        id: string;
        name: string;
        type: 'personal' | 'shared';
    };
    reviewTypes: ReviewTypeOption[];
    personalWorkspaceProjectLimit: number;
};

export default function CreateProject({
    personalWorkspaceProjectLimit,
    reviewTypes,
    selectedWorkspace,
}: Props) {
    const form = useForm({
        name: '',
        review_type: 'systematic_review' as ReviewType,
        research_question: '',
        background: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post('/projects');
    };

    return (
        <>
            <Head title="Create project" />

            <PageShell>
                <PageHeader
                    eyebrow="Project setup"
                    title="Create project"
                    description={`Start a protocol-backed review in ${selectedWorkspace.name}.`}
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={dashboard()}>Cancel</Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Project shell</CardTitle>
                            <CardDescription>
                                Create the research container and first protocol
                                draft.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="project-name">Title</Label>
                                    <Input
                                        id="project-name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="AI-assisted screening in primary care reviews"
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="review-type">
                                        Review type
                                    </Label>
                                    <Select
                                        value={form.data.review_type}
                                        onValueChange={(value: ReviewType) =>
                                            form.setData('review_type', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="review-type"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {reviewTypes.map((type) => (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.review_type}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="research-question">
                                        Research question
                                    </Label>
                                    <Textarea
                                        id="research-question"
                                        value={form.data.research_question}
                                        onChange={(event) =>
                                            form.setData(
                                                'research_question',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="What evidence supports..."
                                        className="min-h-28"
                                    />
                                    <InputError
                                        message={form.errors.research_question}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="background">
                                        Background and rationale
                                    </Label>
                                    <Textarea
                                        id="background"
                                        value={form.data.background}
                                        onChange={(event) =>
                                            form.setData(
                                                'background',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Briefly explain why this review is needed."
                                        className="min-h-28"
                                    />
                                    <InputError
                                        message={form.errors.background}
                                    />
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        Create project
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={dashboard()}>
                                            Back to dashboard
                                        </Link>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <aside className="space-y-3">
                        <WorkflowStepCard
                            step={1}
                            title="Project shell"
                            description="Create the workspace-bound project and owner membership."
                            status="current"
                            meta={
                                selectedWorkspace.type === 'personal'
                                    ? `${personalWorkspaceProjectLimit} active-project limit`
                                    : 'Shared workspace'
                            }
                        />
                        <WorkflowStepCard
                            step={2}
                            title="Protocol draft"
                            description="Record the question, criteria, providers, and review policies."
                            status="pending"
                        />
                        <WorkflowStepCard
                            step={3}
                            title="Ready for search"
                            description="Search remains locked until the protocol is complete."
                            status="pending"
                        />
                        <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                            <div className="flex gap-2">
                                <FolderPlus className="mt-0.5 size-4" />
                                <span>Project visibility is role-scoped.</span>
                            </div>
                            <div className="flex gap-2">
                                <FileText className="mt-0.5 size-4" />
                                <span>
                                    Every project starts with a protocol.
                                </span>
                            </div>
                            <div className="flex gap-2">
                                <ShieldCheck className="mt-0.5 size-4" />
                                <span>Protocol changes are audit-ready.</span>
                            </div>
                        </div>
                    </aside>
                </div>
            </PageShell>
        </>
    );
}

CreateProject.layout = {
    breadcrumbs: [
        {
            title: 'Create project',
            href: '/projects/create',
        },
    ],
};

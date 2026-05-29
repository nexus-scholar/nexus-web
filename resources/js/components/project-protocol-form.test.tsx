import type { InertiaFormProps } from '@inertiajs/react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { ProjectProtocolForm } from '@/components/project-protocol-form';
import type {
    ProjectProtocolFormData,
    ReviewTypeOption,
} from '@/components/project-protocol-form';

const reviewTypes: ReviewTypeOption[] = [
    { value: 'systematic_review', label: 'Systematic review' },
    { value: 'scoping_review', label: 'Scoping review' },
];

const initialData: ProjectProtocolFormData = {
    ai_screening_policy: 'human_only',
    audit_reason: '',
    background: 'The lab needs a traceable protocol.',
    date_range_end: '2026-05-29',
    date_range_start: '2024-01-01',
    exclusion_criteria: '',
    full_text_policy: 'optional',
    inclusion_criteria: 'Peer-reviewed evidence review studies.',
    intent: 'save',
    language_policy: 'English-language records.',
    min_reviewer_count: 2,
    no_date_limit: false,
    research_question: 'How well does AI assist evidence screening?',
    review_type: 'systematic_review',
    target_providers: 'openalex, crossref',
    title: 'AI Screening in Primary Care Reviews',
};

type HarnessProps = {
    canCompleteProtocol?: boolean;
    canUpdateProtocol?: boolean;
    initial?: ProjectProtocolFormData;
    isLocked?: boolean;
    onSubmitIntent?: (intent: ProjectProtocolFormData['intent']) => void;
};

function ProjectProtocolFormHarness({
    canCompleteProtocol = true,
    canUpdateProtocol = true,
    initial = initialData,
    isLocked = false,
    onSubmitIntent = vi.fn(),
}: HarnessProps) {
    const [data, setDataState] = useState(initial);

    const setData = ((key: keyof ProjectProtocolFormData, value: unknown) => {
        setDataState((current) => ({
            ...current,
            [key]: value,
        }));
    }) as InertiaFormProps<ProjectProtocolFormData>['setData'];

    const form = {
        data,
        errors: {},
        processing: false,
        setData,
    } as unknown as InertiaFormProps<ProjectProtocolFormData>;

    return (
        <ProjectProtocolForm
            canCompleteProtocol={canCompleteProtocol}
            canUpdateProtocol={canUpdateProtocol}
            form={form}
            formErrors={{}}
            isLocked={isLocked}
            reviewTypes={reviewTypes}
            onSubmitIntent={onSubmitIntent}
        />
    );
}

describe('ProjectProtocolForm', () => {
    it('renders workflow 2 protocol sections and provider tags', async () => {
        const user = userEvent.setup();

        render(<ProjectProtocolFormHarness />);

        expect(screen.getByText('Review definition')).toBeInTheDocument();
        expect(screen.getByText('Eligibility criteria')).toBeInTheDocument();
        expect(screen.getByText('Search readiness')).toBeInTheDocument();

        const providerGroup = screen.getByRole('group', {
            name: 'Target providers',
        });

        expect(
            within(providerGroup).getByRole('button', {
                name: 'Remove OpenAlex',
            }),
        ).toBeInTheDocument();
        expect(
            within(providerGroup).getByRole('button', {
                name: 'Remove Crossref',
            }),
        ).toBeInTheDocument();

        await user.click(
            within(providerGroup).getByRole('button', {
                name: 'DOAJ Available by default',
            }),
        );

        expect(
            within(providerGroup).getByRole('button', { name: 'Remove DOAJ' }),
        ).toBeInTheDocument();
    });

    it('submits save and complete intentions', async () => {
        const user = userEvent.setup();
        const onSubmitIntent = vi.fn();

        render(<ProjectProtocolFormHarness onSubmitIntent={onSubmitIntent} />);

        await user.click(screen.getByRole('button', { name: 'Save draft' }));
        await user.click(screen.getByRole('button', { name: 'Mark complete' }));

        expect(onSubmitIntent).toHaveBeenNthCalledWith(1, 'save');
        expect(onSubmitIntent).toHaveBeenNthCalledWith(2, 'complete');
    });

    it('keeps reviewer read-only workflow controls disabled', () => {
        render(
            <ProjectProtocolFormHarness
                canCompleteProtocol={false}
                canUpdateProtocol={false}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Save draft' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Mark complete' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', {
                name: 'PubMed Available by default',
            }),
        ).toBeDisabled();
    });
});

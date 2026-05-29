import type { InertiaFormProps } from '@inertiajs/react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { ProjectSearchPlanForm } from '@/components/project-search-plan-form';
import type { ProjectSearchPlanFormData } from '@/components/project-search-plan-form';

const initialData: ProjectSearchPlanFormData = {
    default_providers: 'openalex, crossref',
    default_result_limit: 50,
    default_year_from: '2020',
    default_year_to: '2026',
    include_raw_data: false,
    queries: [
        {
            id: 'query-1',
            include_raw_data: false,
            label: 'Primary intervention search',
            providers: 'openalex, crossref',
            query: '(digital OR mobile) AND primary care',
            query_key: 'primary-search',
            result_limit: 50,
            year_from: '2020',
            year_to: '2026',
        },
    ],
};

type HarnessProps = {
    canRunSearch?: boolean;
    canUpdateSearchPlan?: boolean;
    initial?: ProjectSearchPlanFormData;
    isLocked?: boolean;
    onSubmit?: () => void;
};

function ProjectSearchPlanFormHarness({
    canRunSearch = true,
    canUpdateSearchPlan = true,
    initial = initialData,
    isLocked = false,
    onSubmit = vi.fn(),
}: HarnessProps) {
    const [data, setDataState] = useState(initial);

    const setData = ((key: keyof ProjectSearchPlanFormData, value: unknown) => {
        setDataState((current) => ({
            ...current,
            [key]: value,
        }));
    }) as InertiaFormProps<ProjectSearchPlanFormData>['setData'];

    const form = {
        data,
        errors: {},
        processing: false,
        setData,
    } as unknown as InertiaFormProps<ProjectSearchPlanFormData>;

    return (
        <ProjectSearchPlanForm
            canRunSearch={canRunSearch}
            canUpdateSearchPlan={canUpdateSearchPlan}
            form={form}
            formErrors={{}}
            isLocked={isLocked}
            planStatusLabel="Draft"
            planVersion={1}
            onSubmit={onSubmit}
        />
    );
}

describe('ProjectSearchPlanForm', () => {
    it('renders defaults, provider tags, and the first query row', () => {
        render(<ProjectSearchPlanFormHarness />);

        expect(screen.getByText('Search defaults')).toBeInTheDocument();
        expect(screen.getByText('Query strategy')).toBeInTheDocument();
        expect(screen.getByDisplayValue('primary-search')).toBeInTheDocument();
        expect(
            screen.getByDisplayValue('(digital OR mobile) AND primary care'),
        ).toBeInTheDocument();

        const defaultProviders = screen.getByRole('group', {
            name: 'Default providers',
        });

        expect(
            within(defaultProviders).getByRole('button', {
                name: 'Remove OpenAlex',
            }),
        ).toBeInTheDocument();
        expect(
            within(defaultProviders).getByRole('button', {
                name: 'Remove Crossref',
            }),
        ).toBeInTheDocument();
    });

    it('adds query rows using the current defaults', async () => {
        const user = userEvent.setup();

        render(<ProjectSearchPlanFormHarness />);

        await user.click(screen.getByRole('button', { name: 'Add query' }));

        expect(screen.getByDisplayValue('query-2')).toBeInTheDocument();
        expect(
            screen.getByDisplayValue('Supplementary search 2'),
        ).toBeInTheDocument();
        expect(
            screen.getAllByRole('group', { name: 'Query providers' }),
        ).toHaveLength(2);
    });

    it('submits the search plan draft', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();

        render(<ProjectSearchPlanFormHarness onSubmit={onSubmit} />);

        await user.click(
            screen.getByRole('button', { name: 'Save search plan' }),
        );

        expect(onSubmit).toHaveBeenCalledOnce();
    });

    it('keeps reviewer controls read-only', () => {
        render(
            <ProjectSearchPlanFormHarness
                canRunSearch={false}
                canUpdateSearchPlan={false}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Save search plan' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Add query' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Run all queries blocked' }),
        ).toBeDisabled();

        const defaultProviders = screen.getByRole('group', {
            name: 'Default providers',
        });

        expect(
            within(defaultProviders).getByRole('button', {
                name: 'PubMed Available by default',
            }),
        ).toBeDisabled();
    });
});

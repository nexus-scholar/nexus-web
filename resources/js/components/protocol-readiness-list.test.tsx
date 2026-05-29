import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProtocolReadinessList } from '@/components/protocol-readiness-list';
import type { ProtocolReadinessItem } from '@/types';

const readinessItems: ProtocolReadinessItem[] = [
    {
        id: 'question',
        label: 'Research question',
        description: 'Defines the review scope.',
        complete: true,
    },
    {
        id: 'criteria',
        label: 'Inclusion criteria',
        description: 'Required before search starts.',
        complete: false,
    },
    {
        id: 'notes',
        label: 'Internal notes',
        complete: false,
        required: false,
    },
];

describe('ProtocolReadinessList', () => {
    it('summarizes required protocol progress', () => {
        render(<ProtocolReadinessList items={readinessItems} />);

        expect(screen.getByText('1/2 required')).toHaveClass(
            'text-status-pending',
        );
        expect(screen.getByText('Research question')).toBeVisible();
        expect(screen.getByText('Inclusion criteria')).toBeVisible();
        expect(screen.getByText('Internal notes')).toBeVisible();
    });

    it('marks incomplete required fields without flagging optional fields', () => {
        render(<ProtocolReadinessList items={readinessItems} />);

        expect(screen.getAllByText('Required')).toHaveLength(1);
    });

    it('renders a ready state when all required fields are complete', () => {
        render(
            <ProtocolReadinessList
                items={readinessItems.map((item) => ({
                    ...item,
                    complete: true,
                }))}
            />,
        );

        expect(screen.getByText('2/2 required')).toHaveClass(
            'text-status-include',
        );
    });
});

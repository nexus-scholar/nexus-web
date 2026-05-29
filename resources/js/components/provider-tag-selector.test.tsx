import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import {
    ProviderTagSelector,
    formatProviderValue,
    parseProviderValue,
} from '@/components/provider-tag-selector';

describe('ProviderTagSelector', () => {
    it('normalizes provider aliases from protocol text', () => {
        expect(
            parseProviderValue('openalex, semantic-scholar, unknown, doaj'),
        ).toEqual(['openalex', 'semantic_scholar', 'doaj']);
    });

    it('toggles available provider tags', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        render(
            <>
                <span id="providers-label">Providers</span>
                <ProviderTagSelector
                    labelId="providers-label"
                    value="openalex, crossref"
                    onChange={onChange}
                />
            </>,
        );

        const group = screen.getByRole('group', { name: 'Providers' });

        expect(
            within(group).getByRole('button', { name: 'Remove OpenAlex' }),
        ).toBeInTheDocument();
        expect(
            within(group).getByRole('button', { name: 'Remove Crossref' }),
        ).toBeInTheDocument();

        await user.click(
            within(group).getByRole('button', {
                name: 'PubMed Available by default',
            }),
        );

        expect(onChange).toHaveBeenCalledWith(
            formatProviderValue(['openalex', 'crossref', 'pubmed']),
        );
    });

    it('does not change values while disabled', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        render(
            <>
                <span id="providers-label">Providers</span>
                <ProviderTagSelector
                    disabled
                    labelId="providers-label"
                    value="openalex"
                    onChange={onChange}
                />
            </>,
        );

        await user.click(
            screen.getByRole('button', {
                name: 'DOAJ Available by default',
            }),
        );

        expect(onChange).not.toHaveBeenCalled();
    });
});

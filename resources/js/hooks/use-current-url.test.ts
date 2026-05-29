import type * as InertiaReact from '@inertiajs/react';
import { renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { useCurrentUrl } from '@/hooks/use-current-url';

const mockPage = vi.hoisted(() => ({
    url: '/workspaces/active/members?tab=pending',
}));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaReact>();

    return {
        ...actual,
        usePage: () => mockPage,
    };
});

describe('useCurrentUrl', () => {
    it('normalizes the current Inertia URL to a pathname', () => {
        const { result } = renderHook(() => useCurrentUrl());

        expect(result.current.currentUrl).toBe('/workspaces/active/members');
    });

    it('compares relative and absolute route hrefs', () => {
        const { result } = renderHook(() => useCurrentUrl());

        expect(result.current.isCurrentUrl('/workspaces/active/members')).toBe(
            true,
        );
        expect(
            result.current.isCurrentUrl(
                'https://nexusscholar.test/workspaces/active/members',
            ),
        ).toBe(true);
        expect(result.current.isCurrentUrl('/dashboard')).toBe(false);
    });

    it('matches parent routes when requested', () => {
        const { result } = renderHook(() => useCurrentUrl());

        expect(result.current.isCurrentOrParentUrl('/workspaces')).toBe(true);
        expect(result.current.whenCurrentUrl('/dashboard', 'yes', 'no')).toBe(
            'no',
        );
    });
});

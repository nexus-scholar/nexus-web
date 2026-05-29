import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SettingsSection } from '@/components/settings-section';

describe('SettingsSection', () => {
    it('renders a compact settings panel with title and description', () => {
        render(
            <SettingsSection
                title="Profile"
                description="Name and email used across workspaces."
            >
                <button>Save profile</button>
            </SettingsSection>,
        );

        expect(screen.getByText('Profile')).toBeInTheDocument();
        expect(
            screen.getByText('Name and email used across workspaces.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save profile' }),
        ).toBeInTheDocument();
    });

    it('applies danger styling for destructive settings sections', () => {
        const { container } = render(
            <SettingsSection title="Delete account" tone="danger">
                <button>Delete account</button>
            </SettingsSection>,
        );

        expect(container.firstChild).toHaveClass('border-destructive/25');
    });
});

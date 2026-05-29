import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import { SettingsSection } from '@/components/settings-section';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

const EmptyState = () => {
    return (
        <div className="rounded-md border border-dashed p-6 text-center">
            <div className="mx-auto mb-3 flex size-10 items-center justify-center rounded-md bg-muted">
                <KeyRound className="size-5 text-muted-foreground" />
            </div>
            <p className="text-sm font-medium">No passkeys yet</p>
            <p className="mt-1 text-xs text-muted-foreground">
                Add one for passwordless sign-in.
            </p>
        </div>
    );
};

export default function ManagePasskeys(props: Props) {
    const passkeys = props.passkeys ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <SettingsSection
            title="Passkeys"
            description="Manage passwordless sign-in methods for this account."
        >
            <div className="space-y-4">
                <div className="overflow-hidden rounded-lg border border-border">
                    {passkeys.length > 0 ? (
                        passkeys.map((passkey) => (
                            <PasskeyItem
                                key={passkey.id}
                                passkey={passkey}
                                onDelete={handleDelete}
                            />
                        ))
                    ) : (
                        <EmptyState />
                    )}
                </div>

                <PasskeyRegistration onSuccess={handleRegisterSuccess} />
            </div>
        </SettingsSection>
    );
}

import { useForm } from '@inertiajs/react';
import { FileLock2 } from 'lucide-react';
import type { FormEventHandler } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type LockForm = {
    reason: string;
    corpus_lock?: string;
};

type DedupLockDialogProps = {
    actionUrl: string;
    disabled: boolean;
    blockedReason: string | null;
};

export function DedupLockDialog({
    actionUrl,
    blockedReason,
    disabled,
}: DedupLockDialogProps) {
    const form = useForm<LockForm>({
        reason: '',
        corpus_lock: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        form.post(actionUrl, {
            preserveScroll: true,
        });
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" disabled={disabled}>
                    <FileLock2 className="size-4" />
                    Lock corpus
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Lock corpus</DialogTitle>
                    <DialogDescription>
                        Create a representative-only snapshot for screening.
                    </DialogDescription>
                </DialogHeader>

                <form className="space-y-4" onSubmit={submit}>
                    {blockedReason && (
                        <div className="rounded-md border border-status-conflict/30 bg-status-conflict-bg px-3 py-2 text-sm text-status-conflict">
                            {blockedReason}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="lock-reason">Audit reason</Label>
                        <Textarea
                            id="lock-reason"
                            value={form.data.reason}
                            onChange={(event) => {
                                form.setData('reason', event.target.value);
                            }}
                            placeholder="Example: Deduplication reviewed and corpus approved for title and abstract screening."
                        />
                        <InputError message={form.errors.reason} />
                        <InputError message={form.errors.corpus_lock} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={disabled || form.processing}
                        >
                            <FileLock2 className="size-4" />
                            Confirm lock
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

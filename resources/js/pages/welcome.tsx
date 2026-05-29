import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardCheck, Layers3, ShieldCheck, UsersRound } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { WorkspaceStatusBadge } from '@/components/workspace-status-badge';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex h-20 w-full max-w-6xl items-center justify-between px-6">
                    <Link
                        href={auth.user ? dashboard() : login()}
                        className="flex items-center gap-3 font-semibold"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-brand text-brand-foreground shadow-sm">
                            <AppLogoIcon className="size-5" />
                        </span>
                        <span>Nexus Scholar</span>
                    </Link>

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto grid min-h-[calc(100vh-5rem)] w-full max-w-6xl grid-cols-1 gap-10 px-6 py-10 lg:grid-cols-[minmax(0,1fr)_minmax(390px,0.9fr)] lg:items-center">
                    <section className="max-w-2xl">
                        <p className="mb-4 text-sm font-medium text-brand">
                            Systematic review workspace
                        </p>
                        <h1 className="text-4xl leading-tight font-semibold tracking-normal text-balance sm:text-5xl">
                            Nexus Scholar
                        </h1>
                        <p className="mt-5 max-w-xl text-base leading-7 text-muted-foreground">
                            Coordinate research teams, screen evidence, preserve
                            decisions, and keep a complete audit trail from
                            protocol through export.
                        </p>

                        <div className="mt-8 flex flex-wrap items-center gap-3">
                            {auth.user ? (
                                <Button size="lg" asChild>
                                    <Link href={dashboard()}>
                                        Open dashboard
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button size="lg" asChild>
                                        <Link href={register()}>
                                            Create account
                                        </Link>
                                    </Button>
                                    <Button size="lg" variant="outline" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                </>
                            )}
                        </div>

                        <div className="mt-12 grid gap-5 sm:grid-cols-3">
                            <div>
                                <UsersRound className="mb-3 size-5 text-status-import" />
                                <h2 className="text-sm font-semibold">
                                    Team workspaces
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Invite collaborators and keep project access
                                    scoped to the right lab.
                                </p>
                            </div>
                            <div>
                                <ClipboardCheck className="mb-3 size-5 text-status-include" />
                                <h2 className="text-sm font-semibold">
                                    Review flow
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Move from protocol to screening with
                                    consistent decisions.
                                </p>
                            </div>
                            <div>
                                <ShieldCheck className="mb-3 size-5 text-status-audit" />
                                <h2 className="text-sm font-semibold">
                                    Audit trail
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Preserve changes, reasons, and exports for
                                    reproducible work.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        aria-label="Nexus Scholar review workspace preview"
                        className="rounded-lg border border-border bg-card shadow-sm"
                    >
                        <div className="flex items-center justify-between border-b border-border px-5 py-4">
                            <div>
                                <p className="text-sm font-semibold">
                                    Evidence Synthesis Lab
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Full systematic review
                                </p>
                            </div>
                            <WorkspaceStatusBadge status="active" />
                        </div>

                        <div className="grid grid-cols-3 border-b border-border text-sm">
                            <div className="px-5 py-4">
                                <p className="text-2xl font-semibold">1,248</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    imported records
                                </p>
                            </div>
                            <div className="border-x border-border px-5 py-4">
                                <p className="text-2xl font-semibold">312</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    duplicates
                                </p>
                            </div>
                            <div className="px-5 py-4">
                                <p className="text-2xl font-semibold">64%</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    screened
                                </p>
                            </div>
                        </div>

                        <div className="space-y-1 px-5 py-5">
                            {[
                                ['Protocol', 'Research question approved'],
                                ['Import', 'PubMed and Scopus sources stored'],
                                ['Screening', 'Reviewer conflicts pending'],
                                ['Export', 'PRISMA counts queued'],
                            ].map(([step, detail], index) => (
                                <div
                                    key={step}
                                    className="grid grid-cols-[1.5rem_1fr] gap-3 py-3"
                                >
                                    <span className="mt-0.5 flex size-6 items-center justify-center rounded-md bg-muted text-xs font-semibold text-muted-foreground">
                                        {index + 1}
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <Layers3 className="size-4 text-status-import" />
                                            <p className="text-sm font-medium">
                                                {step}
                                            </p>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {detail}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}

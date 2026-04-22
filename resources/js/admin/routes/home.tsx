import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Link, createRoute } from "@tanstack/react-router";
import {
    ArrowRight,
    BookOpenCheck,
    ChefHat,
    CloudCog,
    Code2,
    Database,
    ExternalLink,
    FileSearch,
    KeyRound,
    Layers,
    Lock,
    MessageCircle,
    Send,
    ShieldCheck,
    Sparkles,
} from "lucide-react";
import { Route as rootRoute } from "./__root";

const POSTMAN_DOCS_URL = "https://documenter.getpostman.com/view/21013457/2sBXqFM2oa";
const SWAGGER_URL = "/docs/api";
const REPO_URL = "https://github.com/azka-zaydan/laravel-assessment";

const CRITERIA = [
    {
        id: "a",
        icon: Lock,
        accent: "indigo",
        title: "JWT + Passport + 2FA",
        body: "Passport 13 issues JWT access tokens. Logins with 2FA return a short-lived challenge token that cannot reach protected routes. TOTP via google2fa with 8 bcrypt-hashed recovery codes.",
    },
    {
        id: "b",
        icon: Database,
        accent: "emerald",
        title: "PostgreSQL persistence",
        body: "Postgres 17 is source-of-truth. Every provider response is upserted to PG and mirrored to Redis in a write-through cache with per-endpoint TTLs.",
    },
    {
        id: "c",
        icon: BookOpenCheck,
        accent: "sky",
        title: "Postman + Swagger + Newman",
        body: "Published Postman collection with pm.test assertions; Newman reruns the collection in CI. Scramble auto-generates a live Swagger UI at /docs/api.",
    },
    {
        id: "d",
        icon: Layers,
        accent: "violet",
        title: "9 design patterns",
        body: "Repository · Service · Strategy ×3 · Observer · Singleton · Facade · Pipeline · Adapter · Command — each grounded to a specific file in DESIGN_PATTERNS.md.",
    },
    {
        id: "e",
        icon: CloudCog,
        accent: "cyan",
        title: "Git + CI/CD",
        body: "GitHub Actions runs Pint, PHPStan level 8, Pest (144 tests, ≥70% coverage), TypeScript, Vite build, Newman. Railway auto-deploys main behind Cloudflare.",
    },
    {
        id: "f",
        icon: FileSearch,
        accent: "amber",
        title: "Metadata logging",
        body: "LogApiRequest middleware captures method/path/IP/headers/body/status/duration/ULID to jsonb. Admin UI filters via spatie/query-builder.",
    },
    {
        id: "g",
        icon: MessageCircle,
        accent: "rose",
        title: "Telegram message types",
        body: "Webhook is secret-header-validated and queues to Redis. MessageDispatcher routes callback_query, location, contact, video, photo, text to dedicated Strategy handlers.",
    },
];

const ACCENT_CLASSES: Record<string, { bg: string; text: string; ring: string }> = {
    indigo: { bg: "bg-indigo-50", text: "text-indigo-600", ring: "ring-indigo-100" },
    emerald: { bg: "bg-emerald-50", text: "text-emerald-600", ring: "ring-emerald-100" },
    sky: { bg: "bg-sky-50", text: "text-sky-600", ring: "ring-sky-100" },
    violet: { bg: "bg-violet-50", text: "text-violet-600", ring: "ring-violet-100" },
    cyan: { bg: "bg-cyan-50", text: "text-cyan-600", ring: "ring-cyan-100" },
    amber: { bg: "bg-amber-50", text: "text-amber-600", ring: "ring-amber-100" },
    rose: { bg: "bg-rose-50", text: "text-rose-600", ring: "ring-rose-100" },
};

const STACK_GROUPS: Array<{ label: string; items: string[] }> = [
    {
        label: "Backend",
        items: ["Laravel 13", "PHP 8.3", "Passport 13", "Pest 4", "PHPStan L8"],
    },
    {
        label: "Data",
        items: ["PostgreSQL 17", "Redis 7", "jsonb", "ULID"],
    },
    {
        label: "Frontend",
        items: ["React 19", "TypeScript 5", "Vite 8", "Tailwind 4", "shadcn/ui", "TanStack"],
    },
    {
        label: "Infra",
        items: ["FrankenPHP", "Railway", "Cloudflare", "GitHub Actions"],
    },
];

function HomePage() {
    return (
        <div className="min-h-screen bg-slate-50">
            {/* Hero */}
            <header className="relative overflow-hidden border-b bg-gradient-to-br from-indigo-50 via-white to-violet-50">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-24 left-1/2 -translate-x-1/2 h-64 w-[48rem] rounded-full bg-indigo-200/30 blur-3xl"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -bottom-24 right-0 h-64 w-96 rounded-full bg-violet-200/30 blur-3xl"
                />

                <div className="relative max-w-6xl mx-auto px-6 pt-16 pb-20 md:pt-24 md:pb-28">
                    <div className="flex flex-col items-center text-center">
                        <div className="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-white/70 backdrop-blur px-3 py-1 text-xs font-medium text-indigo-700 shadow-sm">
                            <Sparkles className="h-3 w-3" />
                            Laravel backend assessment
                        </div>

                        <div className="mt-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30">
                            <ChefHat className="h-7 w-7" />
                        </div>

                        <h1 className="mt-6 text-4xl md:text-6xl font-bold tracking-tight text-slate-900 max-w-3xl">
                            Telegram Culinary Bot API
                        </h1>
                        <p className="mt-5 max-w-2xl text-base md:text-lg text-slate-600 leading-relaxed">
                            A Laravel 13 REST API powering a Telegram bot — restaurant search,
                            nearby discovery, reviews, menus, and rich message-type handling.
                            JWT/Passport auth, TOTP 2FA, PostgreSQL persistence, Redis cache, React
                            admin.
                        </p>

                        <div className="mt-9 flex flex-wrap justify-center gap-3">
                            <Button asChild size="lg" className="gap-2">
                                <Link to="/admin/login">
                                    <Lock className="h-4 w-4" />
                                    Admin login
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </Button>
                            <Button variant="outline" size="lg" className="gap-2" asChild>
                                <a href={SWAGGER_URL} target="_blank" rel="noreferrer">
                                    <BookOpenCheck className="h-4 w-4" />
                                    Swagger
                                    <ExternalLink className="h-3.5 w-3.5" />
                                </a>
                            </Button>
                            <Button variant="outline" size="lg" className="gap-2" asChild>
                                <a href={POSTMAN_DOCS_URL} target="_blank" rel="noreferrer">
                                    <Send className="h-4 w-4" />
                                    Postman docs
                                    <ExternalLink className="h-3.5 w-3.5" />
                                </a>
                            </Button>
                            <Button variant="ghost" size="lg" className="gap-2" asChild>
                                <a href={REPO_URL} target="_blank" rel="noreferrer">
                                    <Code2 className="h-4 w-4" />
                                    GitHub
                                </a>
                            </Button>
                        </div>

                        {/* Stack groups */}
                        <div className="mt-14 w-full max-w-4xl">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {STACK_GROUPS.map((group) => (
                                    <div
                                        key={group.label}
                                        className="rounded-xl border border-slate-200 bg-white/60 backdrop-blur p-4 text-left shadow-sm"
                                    >
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                            {group.label}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {group.items.map((item) => (
                                                <Badge
                                                    key={item}
                                                    variant="secondary"
                                                    className="text-[11px] font-medium"
                                                >
                                                    {item}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {/* Criteria */}
            <section className="max-w-6xl mx-auto px-6 py-16 md:py-20">
                <div className="mb-10 text-center">
                    <h2 className="text-3xl md:text-4xl font-bold tracking-tight text-slate-900">
                        Every grading criterion has a home
                    </h2>
                    <p className="mt-3 max-w-2xl mx-auto text-slate-600">
                        Each of the seven assessment criteria maps to specific files — no
                        hand-waving, no "it's somewhere in the code."
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {CRITERIA.map((c) => {
                        const Icon = c.icon;
                        const accent = ACCENT_CLASSES[c.accent] ?? ACCENT_CLASSES.indigo;
                        return (
                            <Card
                                key={c.id}
                                className="group relative overflow-hidden transition-all hover:shadow-md hover:-translate-y-0.5"
                            >
                                <CardHeader className="gap-3">
                                    <div className="flex items-start justify-between">
                                        <div
                                            className={`h-10 w-10 rounded-xl ${accent.bg} ${accent.text} flex items-center justify-center ring-1 ${accent.ring}`}
                                        >
                                            <Icon className="h-5 w-5" />
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className="text-[10px] font-mono uppercase tracking-wider"
                                        >
                                            criterion {c.id}
                                        </Badge>
                                    </div>
                                    <CardTitle className="text-base">{c.title}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription className="text-sm leading-relaxed">
                                        {c.body}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </section>

            {/* Quick actions */}
            <section className="max-w-6xl mx-auto px-6 pb-20">
                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="relative overflow-hidden border-indigo-200">
                        <div className="absolute top-0 right-0 h-24 w-24 rounded-full bg-indigo-100 blur-2xl" />
                        <CardHeader className="relative">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="h-4 w-4 text-indigo-600" />
                                <CardTitle className="text-base">Admin UI</CardTitle>
                            </div>
                            <CardDescription>
                                Seed credentials{" "}
                                <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px] font-mono">
                                    admin@example.test
                                </code>{" "}
                                /{" "}
                                <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px] font-mono">
                                    password
                                </code>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="relative">
                            <Button size="sm" asChild>
                                <Link to="/admin/login">Open admin →</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <KeyRound className="h-4 w-4 text-sky-600" />
                                <CardTitle className="text-base">Try the API</CardTitle>
                            </div>
                            <CardDescription>
                                Every request has pm.test assertions and auto-extracts tokens.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="secondary" size="sm" asChild>
                                <a href={POSTMAN_DOCS_URL} target="_blank" rel="noreferrer">
                                    Run in Postman →
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Code2 className="h-4 w-4 text-violet-600" />
                                <CardTitle className="text-base">Source &amp; docs</CardTitle>
                            </div>
                            <CardDescription>
                                README and DESIGN_PATTERNS.md — file pointers for every pattern.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="secondary" size="sm" asChild>
                                <a href={REPO_URL} target="_blank" rel="noreferrer">
                                    Repo →
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t bg-white">
                <div className="max-w-6xl mx-auto px-6 py-6 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                    <span>Laravel backend assessment — deployed on Railway behind Cloudflare.</span>
                    <span className="font-mono text-slate-400">laravel.catatkeu.app</span>
                </div>
            </footer>
        </div>
    );
}

export const Route = createRoute({
    getParentRoute: () => rootRoute,
    path: "/",
    component: HomePage,
});

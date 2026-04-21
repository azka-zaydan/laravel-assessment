import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Link, createRoute } from "@tanstack/react-router";
import {
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
} from "lucide-react";
import { Route as rootRoute } from "./__root";

const POSTMAN_DOCS_URL = "https://documenter.getpostman.com/view/21013457/2sBXqFM2oa";
const REPO_URL = "https://github.com/azka-zaydan/laravel-assessment";

const CRITERIA = [
    {
        id: "a",
        icon: Lock,
        title: "JWT + Passport + 2FA",
        body: "Laravel Passport 13 issues JWT access tokens. Login with 2FA returns a short-lived challenge token (firebase/php-jwt) that cannot reach protected routes. TOTP enrolment via google2fa with 8 bcrypt-hashed recovery codes.",
    },
    {
        id: "b",
        icon: Database,
        title: "PostgreSQL persistence",
        body: "Postgres 17 managed by Railway. Write-through cache — every Zomato response is upserted to Postgres and mirrored in Redis. Restaurants, reviews, menu items, api logs, telegram users — everything in pgsql.",
    },
    {
        id: "c",
        icon: BookOpenCheck,
        title: "Postman + Scramble docs + automated tests",
        body: "Published Postman collection with pm.test assertions on every request; Newman runs the same collection in CI. Scramble auto-generates a live Swagger UI at /docs/api.",
    },
    {
        id: "d",
        icon: Layers,
        title: "9 design patterns",
        body: "Repository · Service · Strategy (3 sites) · Observer · Singleton · Facade · Pipeline · Adapter · Command. Documented in DESIGN_PATTERNS.md with file pointers.",
    },
    {
        id: "e",
        icon: CloudCog,
        title: "Git + CI/CD",
        body: "GitHub Actions runs Pint, PHPStan level 8, Pest (144 tests), TypeScript, Vite build, Newman. Railway's native GitHub integration auto-deploys main behind Cloudflare.",
    },
    {
        id: "f",
        icon: FileSearch,
        title: "Metadata logging + admin UI",
        body: "LogApiRequest middleware stores method/path/IP/headers/body/status/duration/ULID request_id as jsonb rows — redaction via a recursive deny-list walk. Admin UI at /admin/logs filters with spatie/query-builder.",
    },
    {
        id: "g",
        icon: MessageCircle,
        title: "Telegram message handlers",
        body: "Webhook (secret-header-validated) queues to Redis; MessageDispatcher routes callback_query, location, contact, video, photo, text to dedicated Strategy handlers. Account linking via /link <code>.",
    },
];

const STACK = [
    { label: "Laravel 13", tone: "laravel" },
    { label: "PHP 8.3", tone: "php" },
    { label: "Passport 13", tone: "passport" },
    { label: "Pest 4", tone: "test" },
    { label: "PostgreSQL 17", tone: "db" },
    { label: "Redis 7", tone: "db" },
    { label: "FrankenPHP", tone: "infra" },
    { label: "React 19", tone: "react" },
    { label: "TypeScript 5", tone: "react" },
    { label: "Vite 8", tone: "react" },
    { label: "Tailwind 4", tone: "react" },
    { label: "shadcn/ui", tone: "react" },
    { label: "TanStack Router / Query", tone: "react" },
    { label: "Railway", tone: "infra" },
    { label: "Cloudflare", tone: "infra" },
];

function HomePage() {
    return (
        <div className="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900">
            {/* Hero */}
            <header className="border-b bg-white/70 dark:bg-slate-950/70 backdrop-blur">
                <div className="max-w-6xl mx-auto px-6 py-14 md:py-20">
                    <div className="flex items-center gap-2 mb-5">
                        <div className="inline-flex items-center justify-center h-9 w-9 rounded-md bg-indigo-600 text-white">
                            <ChefHat className="h-5 w-5" />
                        </div>
                        <span className="text-sm font-medium text-slate-500 dark:text-slate-400">
                            laravel-assessment
                        </span>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Telegram Culinary Bot API
                    </h1>
                    <p className="mt-4 max-w-2xl text-lg text-slate-600 dark:text-slate-400">
                        A RESTful Laravel 13 API powering a Telegram bot — restaurant search, nearby
                        discovery, reviews, menus, and rich message-type handling — with
                        JWT/Passport auth, TOTP 2FA, PostgreSQL persistence, Redis caching, and a
                        React admin log viewer.
                    </p>

                    <div className="mt-8 flex flex-wrap gap-3">
                        <Button asChild>
                            <Link to="/admin/login">
                                <Lock className="h-4 w-4 mr-1.5" />
                                Admin login
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href="/docs/api" target="_blank" rel="noreferrer">
                                <BookOpenCheck className="h-4 w-4 mr-1.5" />
                                Swagger (Scramble)
                                <ExternalLink className="h-3.5 w-3.5 ml-1" />
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={POSTMAN_DOCS_URL} target="_blank" rel="noreferrer">
                                <Send className="h-4 w-4 mr-1.5" />
                                Postman docs
                                <ExternalLink className="h-3.5 w-3.5 ml-1" />
                            </a>
                        </Button>
                        <Button variant="ghost" asChild>
                            <a href={REPO_URL} target="_blank" rel="noreferrer">
                                <Code2 className="h-4 w-4 mr-1.5" />
                                GitHub
                                <ExternalLink className="h-3.5 w-3.5 ml-1" />
                            </a>
                        </Button>
                    </div>

                    <div className="mt-10 flex flex-wrap gap-1.5">
                        {STACK.map((s) => (
                            <Badge key={s.label} variant="secondary" className="text-[11px]">
                                {s.label}
                            </Badge>
                        ))}
                    </div>
                </div>
            </header>

            {/* Criteria */}
            <section className="max-w-6xl mx-auto px-6 py-14 md:py-20">
                <div className="mb-8">
                    <h2 className="text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Assessment criteria → implementation
                    </h2>
                    <p className="mt-2 text-slate-600 dark:text-slate-400">
                        Every grading criterion has a concrete home in the codebase.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {CRITERIA.map((c) => {
                        const Icon = c.icon;
                        return (
                            <Card key={c.id}>
                                <CardHeader className="gap-2">
                                    <div className="flex items-center gap-3">
                                        <div className="h-8 w-8 rounded-md bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                            <Icon className="h-4 w-4" />
                                        </div>
                                        <Badge variant="outline" className="text-[10px] font-mono">
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
                    <Card className="border-indigo-200 dark:border-indigo-900">
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <ShieldCheck className="h-4 w-4 text-indigo-600" />
                                Admin UI
                            </CardTitle>
                            <CardDescription>
                                Seed credentials:{" "}
                                <code className="text-xs font-mono">admin@example.test</code> /{" "}
                                <code className="text-xs font-mono">password</code>.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="secondary" size="sm" asChild>
                                <Link to="/admin/login">Open admin</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <KeyRound className="h-4 w-4 text-indigo-600" />
                                Try the API
                            </CardTitle>
                            <CardDescription>
                                Open the Postman collection — tokens auto-populate on register &
                                login.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="secondary" size="sm" asChild>
                                <a href={POSTMAN_DOCS_URL} target="_blank" rel="noreferrer">
                                    Run in Postman
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Code2 className="h-4 w-4 text-indigo-600" />
                                Source &amp; docs
                            </CardTitle>
                            <CardDescription>
                                README · PLAN.md · DESIGN_PATTERNS.md · RAILWAY_SETUP.md
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="secondary" size="sm" asChild>
                                <a href={REPO_URL} target="_blank" rel="noreferrer">
                                    Repo
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <footer className="border-t bg-white/60 dark:bg-slate-950/60">
                <div className="max-w-6xl mx-auto px-6 py-6 text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-2">
                    <span>
                        Built for the Laravel backend assessment. Deployed on Railway behind
                        Cloudflare.
                    </span>
                    <span className="font-mono">laravel.catatkeu.app</span>
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

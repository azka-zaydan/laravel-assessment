# Telegram Culinary Bot API

A RESTful Laravel 13 API powering a Telegram culinary bot — supporting restaurant search, nearby discovery, user reviews, and rich message-type handling — with JWT/Passport auth, 2FA, PostgreSQL persistence, Redis caching, and a React 19 admin log viewer.

---

## Assessment Criteria → Implementation Map

This table maps each grading criterion directly to the code that satisfies it.

| # | Criterion | Implementation |
|---|-----------|----------------|
| **a** | JWT/Passport authentication + 2FA | Laravel Passport 13 issues JWT access tokens. `POST /api/login` returns either a full token (2FA off) or a short-lived challenge token (`firebase/php-jwt`, `JWT_CHALLENGE_SECRET`). TOTP via `pragmarx/google2fa-laravel`. Full flow in [`app/Http/Controllers/Api/AuthController.php`](app/Http/Controllers/Api/AuthController.php), [`app/Http/Controllers/Api/TwoFactorController.php`](app/Http/Controllers/Api/TwoFactorController.php), [`app/Services/Auth/`](app/Services/Auth/), [`app/Http/Middleware/Require2FA.php`](app/Http/Middleware/Require2FA.php). |
| **b** | All data in PostgreSQL | `DB_CONNECTION=pgsql` in [`.env.example`](.env.example). Tables: `users`, `restaurants`, `reviews`, `menu_items`, `api_logs`, `telegram_users`, `user_submissions`, `oauth_*`. Write-through caching via [`app/Repositories/RestaurantRepository.php`](app/Repositories/RestaurantRepository.php): every provider response is upserted to Postgres and mirrored in Redis. |
| **c** | Postman docs + automated tests | Collection at `Postman/collection.json`, environment at `Postman/environment.json`. Published docs: https://documenter.getpostman.com/view/21013457/2sBXqFM2oa. Auto-generated Swagger UI via Scramble at `/docs/api`. Newman integration tests run in CI (job `postman` in [`.github/workflows/ci.yml`](.github/workflows/ci.yml)). |
| **d** | Design patterns | 9 patterns documented in [`DESIGN_PATTERNS.md`](DESIGN_PATTERNS.md): Repository, Service, Strategy (3 sites), Observer, Singleton, Facade, Pipeline, Adapter, Command. |
| **e** | Git + CI/CD | GitHub Actions at [`.github/workflows/ci.yml`](.github/workflows/ci.yml) (Pint · PHPStan level 8 · Pest 144 tests · TypeScript/Biome/Vite · Newman). Railway native GitHub integration deploys `main` automatically after all gates pass. |
| **f** | Request metadata logging + admin UI | [`app/Http/Middleware/LogApiRequest.php`](app/Http/Middleware/LogApiRequest.php) captures method, path, IP, user-agent, headers (jsonb), body (jsonb), response status, duration, request_id (ULID). Admin API at `GET /api/admin/api-logs` ([`app/Http/Controllers/Api/Admin/ApiLogController.php`](app/Http/Controllers/Api/Admin/ApiLogController.php)). React admin pages at [`resources/js/admin/routes/logs.tsx`](resources/js/admin/routes/logs.tsx) (filter, paginate, table) and [`resources/js/admin/routes/login.tsx`](resources/js/admin/routes/login.tsx). |
| **g** | Multiple Telegram message types | [`app/Services/Telegram/MessageDispatcher.php`](app/Services/Telegram/MessageDispatcher.php) dispatches by type in deterministic order. Handlers for **text**, **location**, **contact**, **video**, **photo**, and **callback_query** in [`app/Services/Telegram/Handlers/`](app/Services/Telegram/Handlers/). |

---

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Framework | Laravel | 13.x |
| Runtime | PHP | 8.3 |
| Auth | Laravel Passport | 13.x |
| 2FA | pragmarx/google2fa-laravel | 3.0.x |
| JWT (challenge token) | firebase/php-jwt | 7.x |
| API query builder | spatie/laravel-query-builder | 7.2.x |
| API docs | dedoc/scramble | 0.13.x |
| Testing | Pest | 4.x |
| Static analysis | Larastan (PHPStan) | 3.x — level 8 |
| Code style | Laravel Pint | 1.x |
| Frontend framework | React | 19.x |
| Frontend bundler | Vite | 8.x |
| Frontend styles | Tailwind CSS | 4.x |
| Frontend routing | TanStack Router | 1.x |
| Frontend data | TanStack Query | 5.x |
| Frontend UI | shadcn/ui (Radix primitives) | — |
| Frontend lint/format | Biome | 1.9.x |
| Database | PostgreSQL | 17 |
| Cache / Queue broker | Redis | 7 |
| App server | FrankenPHP (worker mode) | — |
| Deploy platform | Railway | — |
| CDN / TLS proxy | Cloudflare | — |

---

## Architecture

The application is a single Laravel 13 API with three Railway services: `web` (FrankenPHP), `worker` (`queue:work`), and `scheduler` (`schedule:work`).

**Request layering:**

```
HTTP Request
  └── Middleware chain
        (ValidateTelegramSecret | auth:api → require_2fa → LogApiRequest)
        └── Controller  (validates input, delegates — no business logic)
              └── Service  (business logic)
                    └── Repository  (data access + write-through cache)
                          └── PostgreSQL 17  ←→  Redis 7
```

**Write-through cache:** `RestaurantRepository` upserts each provider response into Postgres (source of truth) and writes to Redis with per-endpoint TTLs — 5 min for search, 1 h for detail, 24 h for static data. Cache keys: `zomato:<endpoint>:<params>`.

**Async processing:** The Telegram webhook controller dispatches `ProcessTelegramUpdate` to the Redis queue and returns `200` immediately. `LogApiRequestJob` is similarly async so logging adds zero latency to the HTTP response path.

---

## Design Patterns

Nine patterns are documented with exact file locations and purpose in [`DESIGN_PATTERNS.md`](DESIGN_PATTERNS.md):

Repository · Service · Strategy · Observer · Singleton · Facade · Pipeline · Adapter · Command

---

## Prerequisites

| Tool | Minimum version |
|------|----------------|
| PHP | 8.3 |
| Composer | 2.x |
| Node.js | 22.x |
| npm | 10.x |
| Docker + Docker Compose | Desktop 4.x / Compose v2 |
| Railway CLI | latest — `npm i -g @railway/cli` |
| GitHub CLI | 2.x (optional — for PR workflows) |

---

## Local Setup — Host-based (PHP on your machine)

Use this when you want fast iteration with a local PHP install.

```bash
# 1. Clone
git clone https://github.com/<your-org>/laravel-assessment.git
cd laravel-assessment

# 2. Copy environment file
cp .env.example .env

# 3. Generate Laravel application key
php artisan key:generate

# 4. Generate required secrets — paste output into .env
openssl rand -hex 32   # → TELEGRAM_WEBHOOK_SECRET
openssl rand -hex 64   # → JWT_CHALLENGE_SECRET

# 5. Start Postgres 17 + Redis 7 (Docker, DB only)
docker compose up -d db redis

# 6. Install PHP dependencies
composer install

# 7. Install Node dependencies
npm install

# 8. Run migrations and seed (creates admin user + mock restaurant fixtures)
php artisan migrate --seed

# 9. Install Passport encryption keys + personal-access client
php artisan passport:install

# 10. Start the API server (terminal 1)
php artisan serve

# 11. Start Vite HMR for the admin SPA (terminal 2)
npm run dev

# 12. (Optional) Start queue worker for async jobs (terminal 3)
php artisan queue:listen --tries=1
```

The API is at `http://localhost:8000/api` and the admin UI at `http://localhost:8000/admin`.

Alternatively, run everything at once with:

```bash
composer run dev
```

This uses `concurrently` to run `artisan serve`, `queue:listen`, `pail`, and `vite` in a single terminal.

---

## Local Setup — Full Docker

All five services (app · worker · scheduler · db · redis) start together:

```bash
cp .env.example .env
# Edit .env — add APP_KEY, JWT_CHALLENGE_SECRET, TELEGRAM_* values

docker compose up --build
```

The `vendor/` directory lives in a named Docker volume so host-side `vendor/` does not shadow the container-built one. The app is available at `http://localhost:8000`.

---

## Environment Variables

Full reference is in [`.env.example`](.env.example). Key variables:

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | Yes | Laravel encryption key — `php artisan key:generate` |
| `APP_URL` | Yes | Base URL used in Passport token issuance and Scramble docs |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Yes | PostgreSQL connection |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Yes | Redis connection for cache, queues, sessions |
| `RESTAURANT_PROVIDER` | No | `mock` (default) or `zomato` — use `mock` for local dev and CI |
| `ZOMATO_USER_KEY` | No | Zomato API key; leave empty when using `mock` provider |
| `TELEGRAM_BOT_TOKEN` | Yes (prod) | Token from `@BotFather`; tests use `Http::fake()` so a placeholder works locally |
| `TELEGRAM_WEBHOOK_SECRET` | Yes (prod) | 32-byte hex secret guarding `POST /api/telegram/webhook` — `openssl rand -hex 32` |
| `TELEGRAM_WEBHOOK_URL` | Yes (prod) | Full HTTPS URL Telegram POSTs updates to |
| `JWT_CHALLENGE_SECRET` | Yes | 64-byte hex secret for 2FA challenge tokens — `openssl rand -hex 64` |
| `JWT_CHALLENGE_TTL_MINUTES` | No | Challenge token lifetime in minutes (default: `5`) |
| `SEED_ADMIN_EMAIL` | No | Email for the seeded admin account (default: `admin@example.test`) |
| `SEED_ADMIN_PASSWORD` | No | Password for the seeded admin account (default: `password`) |
| `LOG_REDACT_KEYS` | No | Comma-separated field names redacted from `api_logs` body/headers |
| `API_LOG_RETENTION_DAYS` | No | Days before `logs:prune` deletes old rows (default: `30`) |
| `POSTMAN_API_KEY` | No (CI) | Postman API key for Newman CI job |

---

## Obtaining API Keys

**Telegram bot token**

1. Open Telegram and start a chat with `@BotFather`.
2. Send `/newbot`, follow prompts, copy the token.
3. Set `TELEGRAM_BOT_TOKEN=<token>` in `.env`.

**Zomato API**

The public Zomato Developer API (`developers.zomato.com`) has been shut down — the portal redirects to a POS-only product. `RESTAURANT_PROVIDER=mock` is therefore the default for both local dev and CI. The `ZomatoProvider` class is fully implemented against the archived OpenAPI spec and exercised via `Http::fake()` stubs; see [Deviations from Brief](#deviations-from-original-brief) for full rationale.

**Postman API key**

Required only in CI to drive Newman. Generate at `https://go.postman.co/settings/me/api-keys` and add as the `POSTMAN_API_KEY` GitHub secret.

---

## Running Tests

```bash
# Full Pest suite (144 tests)
./vendor/bin/pest

# PHPStan static analysis — level 8, no tolerance
./vendor/bin/phpstan analyse --memory-limit=2G

# Laravel Pint code-style check (exits non-zero on violations)
./vendor/bin/pint --test

# TypeScript type check
npm run typecheck

# Biome lint
npm run lint

# Production Vite build (verifies no build-time errors)
npm run build

# Newman integration tests (requires Postman/collection.json to exist)
npx newman run Postman/collection.json \
  -e Postman/environment.json \
  --env-var base_url=http://localhost:8000
```

All CI jobs mirror these commands exactly — see [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

---

## Postman

**Published docs URL:** https://documenter.getpostman.com/view/21013457/2sBXqFM2oa

**Public workspace:** https://www.postman.com/gold-capsule-253428/workspace/3286fe3e-15c3-43d6-bdf1-82c67dfc81a7
*(Replace this placeholder after publishing the collection to the public Postman network.)*

**Local import:**

1. In Postman, click **Import** and select `Postman/collection.json`.
2. Import `Postman/environment.json` and activate the **Development** environment.
3. Set `base_url` to `http://localhost:8000`.

**Token variables:**

| Variable | How to populate |
|----------|----------------|
| `admin_token` | Run the **Login** request as the seeded admin — the test script auto-extracts it |
| `user_token` | Run **Register** then **Login** as a regular user |
| `challenge_token` | Returned by **Login** when 2FA is enabled — auto-extracted by test script |
| `telegram_webhook_url` | Your deployed URL, e.g. `https://your-domain.example/api/telegram/webhook` |

Every request includes: HTTP status assertion, JSON-schema validation, and token auto-extraction where applicable.

---

## API Documentation (Swagger / Scramble)

Auto-generated OpenAPI 3.1 documentation is served by `dedoc/scramble` at:

```
GET /docs/api
```

No manual annotation is required — Scramble infers types from FormRequests, API Resources, and PHP return-type hints. The spec reflects the live route table in [`routes/api.php`](routes/api.php).

---

## Telegram Setup

After deploying to a public HTTPS domain:

```bash
php artisan telegram:set-webhook
```

This calls `setWebhook` on the Telegram Bot API using `TELEGRAM_WEBHOOK_URL` and sets the `X-Telegram-Bot-Api-Secret-Token` header guard using `TELEGRAM_WEBHOOK_SECRET`. The `ValidateTelegramSecret` middleware performs a constant-time comparison on every incoming update.

> **HTTPS required.** Telegram refuses plain `http://` webhook URLs. Route your Railway domain through Cloudflare with Full Strict TLS enabled.

**Supported message types:**

| Telegram type | Handler class |
|---------------|---------------|
| Text (plain) + bot commands | `TextHandler` → `CommandRegistry` |
| Location | `LocationHandler` |
| Contact | `ContactHandler` |
| Video | `VideoHandler` |
| Photo | `PhotoHandler` |
| Callback query (inline keyboard) | `CallbackHandler` |

**Bot commands:** `/start` · `/help` · `/search <query>` · `/link <code>`

---

## Admin UI

| Route | Description |
|-------|-------------|
| `/admin/login` | Admin login form (issues Passport token stored in React state + httpOnly refresh cookie) |
| `/admin/logs` | API request log table with column filters, date range, pagination |
| `/admin/2fa/enroll` | TOTP QR code display + one-time recovery code reveal |
| `/admin/2fa/verify` | 2FA challenge verification for admin login flow |

**Default admin credentials** (seeded by `DatabaseSeeder`):

- Email: `SEED_ADMIN_EMAIL` env value (default: `admin@example.test`)
- Password: `SEED_ADMIN_PASSWORD` env value (default: `password`)

Change `SEED_ADMIN_PASSWORD` before seeding in any non-local environment.

**Screenshots:**

![Admin log viewer](./docs/admin-logs.png)
*(Screenshot placeholder — to be added after first deployment.)*

---

## Deployment — Railway + Cloudflare

### Three Railway services, three Dockerfiles

Each service has a dedicated Dockerfile optimised for its workload + a matching [Railway config-as-code](https://docs.railway.com/reference/config-as-code) file at the repo root:

| Service | Config file | Dockerfile | Base image | Extensions | ENTRYPOINT + CMD | Approx size |
|---------|-------------|-----------|-----------|-----------|------------------|------------|
| `web` | [`railway.web.json`](railway.web.json) | [`Dockerfile`](Dockerfile) | `dunglas/frankenphp:latest-php8.3` | pdo_pgsql, redis, bcmath, intl, gd, opcache | `docker/entrypoint.sh` → `frankenphp run ...` (runs migrations) | ~250 MB |
| `queue-laravel` | [`railway.queue.json`](railway.queue.json) | [`Dockerfile.queue`](Dockerfile.queue) | `php:8.3-cli-alpine` | pdo_pgsql, redis, bcmath, opcache | `docker/entrypoint.worker.sh` → `php artisan queue:work redis --tries=3 --timeout=90 --sleep=3` | ~45 MB |
| `cron-laravel` | [`railway.cron.json`](railway.cron.json) | [`Dockerfile.cron`](Dockerfile.cron) | `php:8.3-cli-alpine` | pdo_pgsql, redis, bcmath | `docker/entrypoint.worker.sh` → `php artisan schedule:work` | ~43 MB |

**Why three images instead of one:**

- **5× smaller workers** — dropping FrankenPHP/Caddy/Vite/gd/intl takes compressed image from ~190 MB to ~45 MB. Faster cold starts, faster pulls, smaller surface.
- **OPcache only where it helps** — enabled on `queue-laravel` (long-lived daemon, bytecode reused across every dequeued job). Disabled on `cron-laravel` (`schedule:work` forks a new PHP process per due task — opcache never amortises).
- **Migrations run in exactly one place** — `docker/entrypoint.sh` (web) includes `migrate --force`; `docker/entrypoint.worker.sh` (queue + cron) deliberately omits it. No race on `schema_migrations`.
- **Workers don't need the frontend** — `public/build/*` (Vite output) and the whole Node 22 stage are skipped for queue/cron.
- **Shared `composer.lock`** — all three stages start `FROM composer:2` with the same lock, so dependency versions are identical across images.

`railway.queue.json` and `railway.cron.json` don't set `deploy.startCommand` — the Dockerfile's `ENTRYPOINT + CMD` owns startup behaviour, including the worker-specific entrypoint that waits for Postgres and caches config but skips migrations.

### Initial setup

```bash
# 1. Authenticate
railway login

# 2. Link to your Railway project (or create one on the dashboard)
railway link

# 3. Set required environment variables on the web service
railway variables --service web --set \
  APP_KEY="$(php artisan key:generate --show)" \
  JWT_CHALLENGE_SECRET="$(openssl rand -hex 64)" \
  TELEGRAM_BOT_TOKEN="<your-bot-token>" \
  TELEGRAM_WEBHOOK_SECRET="$(openssl rand -hex 32)" \
  TELEGRAM_WEBHOOK_URL="https://your-domain.example/api/telegram/webhook" \
  SEED_ADMIN_EMAIL="admin@your-domain.com" \
  SEED_ADMIN_PASSWORD="$(openssl rand -base64 16)" \
  RESTAURANT_PROVIDER=mock
```

4. Add **Postgres** and **Redis** plugins from the Railway dashboard — they inject `DATABASE_URL` and `REDIS_URL` automatically.
5. Push to `main`. Railway auto-deploys when CI passes via native GitHub integration (no `RAILWAY_TOKEN` secret needed).

### Adding the queue + cron services (one-time, dashboard)

1. In the Railway dashboard, create two services from the same GitHub repo: `queue-laravel` and `cron-laravel`.
2. For each, open **Settings → Config → Config-as-code Path** and set:
   - `web` → `/railway.web.json`
   - `queue-laravel` → `/railway.queue.json`
   - `cron-laravel` → `/railway.cron.json`
3. In each new service's **Settings → Deploy → Replica Limits**, set modest caps to control cost:
   - `queue-laravel` — 0.25 vCPU, 512 MB (Laravel queue worker RSS sits around 80–180 MB)
   - `cron-laravel` — 0.25 vCPU, 256 MB (mostly idle between minute ticks)
4. Put shared secrets in **Project Settings → Shared Variables** (`APP_KEY`, `DB_*`, `REDIS_*`, `TELEGRAM_*`, `JWT_CHALLENGE_*`, `PASSPORT_*`), then import into all three services. Keeps env drift out of the picture on secret rotation.

Reference shared values from a service variable using `${{ shared.APP_KEY }}`.

### Cloudflare

In your Cloudflare dashboard, add a CNAME record on the zone you want to serve the API from:

| Field | Value |
|-------|-------|
| Name | `laravel` |
| Target | Your Railway-provided domain (e.g. `web.up.railway.app`) |
| Proxy | Enabled (orange cloud) |
| TLS mode | **Full** (not Full (Strict)) |

> Railway's edge certificate is valid for `*.up.railway.app`, not your custom domain. Full (Strict) requires the origin certificate to match the hostname being proxied and will break during edge-certificate rotation. Use plain **Full** — it encrypts Cloudflare ↔ Railway but tolerates Railway's wildcard cert.

After DNS propagates, register the Telegram webhook:

```bash
railway run php artisan telegram:set-webhook
```

---

## Project Structure

```
app/
  Console/Commands/         TelegramSetWebhook, PruneApiLogs
  Http/
    Controllers/Api/        AuthController, TwoFactorController, RestaurantController,
                            TelegramWebhookController, TelegramLinkController,
                            Admin/ApiLogController
    Middleware/             LogApiRequest, Require2FA, RequireTwoFactorConfirmed,
                            ValidateTelegramSecret
    Requests/               FormRequests (Auth, TwoFactor, Restaurants)
    Resources/              UserResource, RestaurantResource, ReviewResource,
                            MenuItemResource, ApiLogResource
  Jobs/                     ProcessTelegramUpdate, LogApiRequestJob, ProcessPhotoSubmission
  Models/                   User, Restaurant, Review, MenuItem, ApiLog,
                            TelegramUser, UserSubmission, UserFavorite, RefreshToken
  Observers/                ApiLogObserver
  Providers/                AppServiceProvider, RestaurantServiceProvider,
                            TelegramServiceProvider
  Repositories/             RestaurantRepository, ApiLogRepository
  Services/
    Auth/                   ChallengeTokenService, TwoFactorService, RefreshTokenService
    Restaurants/            RestaurantService, RestaurantProvider (interface),
                            MockProvider, ZomatoProvider
    Telegram/
      Commands/             CommandRegistry, StartCommand, HelpCommand,
                            SearchCommand, LinkCommand
      Handlers/             TextHandler, LocationHandler, ContactHandler,
                            VideoHandler, PhotoHandler, CallbackHandler
      TelegramBotService.php
      MessageDispatcher.php
      LinkCodeService.php
  Support/                  LogRedactor, ZomatoRateLimiter

resources/js/admin/
  routes/                   login.tsx, logs.tsx, enroll-2fa.tsx, verify-2fa.tsx
  components/               shadcn/ui wrappers
  lib/                      axios client, type utilities

tests/
  Feature/
    Auth/                   Registration, login, token refresh, logout
    TwoFactor/              Enable, confirm, verify, recovery codes
    Restaurants/            Search, detail, reviews, menu, nearby
    Telegram/               Webhook dispatch, link flow, each handler type
    Admin/                  API log endpoint filters + pagination
    Logging/                LogApiRequest middleware
  Unit/
    Support/                LogRedactor redaction rules

.github/workflows/ci.yml    CI pipeline (Pint · PHPStan · Pest · TS/Biome · Newman)
Caddyfile                   FrankenPHP Caddy configuration
Dockerfile                  Multi-stage: Composer deps → npm build → FrankenPHP runtime
docker-compose.yml          Local dev: app + worker + scheduler + db + redis
```

---

## CI/CD

Pipeline: [`.github/workflows/ci.yml`](.github/workflows/ci.yml) — triggers on push to `main`/`develop` and on pull requests.

| Job | Command / Action |
|-----|-----------------|
| `lint` | `./vendor/bin/pint --test` |
| `static` | `./vendor/bin/phpstan analyse` (level 8) |
| `test-php` | Pest against Postgres 17 + Redis 7 service containers; coverage ≥ 70% |
| `test-frontend` | `npm run typecheck` + `npm run lint` + `npm run build` |
| `postman` | Newman against ephemeral `php artisan serve` — skipped when `Postman/collection.json` absent |
| `deploy-notify` | Confirmation gate on `main`; Railway deploys via native GH integration |

**Required GitHub Secrets:**

| Secret | Used by job(s) |
|--------|---------------|
| `APP_KEY` | `test-php`, `postman` |
| `JWT_CHALLENGE_SECRET` | `test-php`, `postman` |
| `TELEGRAM_BOT_TOKEN` | `test-php`, `postman` |
| `TELEGRAM_WEBHOOK_SECRET` | `test-php`, `postman` |
| `SEED_ADMIN_PASSWORD` | `test-php`, `postman` |
| `POSTMAN_API_KEY` | `postman` (optional Newman reporter) |

---

## Deviations from Original Brief

| Brief specified | What we use | Rationale |
|----------------|------------|-----------|
| Laravel 11 | **Laravel 13** | Passport 13 requires Laravel 13. Using the current release avoids immediate upgrade debt and aligns all package versions with their latest stable releases. |
| Pest 3 | **Pest 4** | Pest 4 ships with PHP 8.3 attribute-based datasets and improved parallel coverage reporting. No breaking API changes affect this test suite. |
| PostgreSQL 16 | **PostgreSQL 17** | Railway's managed plugin defaults to PG 17. Running the same major version locally eliminates JSONB and generated-column behaviour differences between environments. |
| Larastan level 6 | **Level 8** | Level 8 catches a meaningfully larger class of null-safety and type-narrowing bugs. Satisfying it required roughly two extra hours but produced several real bug fixes caught at analysis time rather than runtime. |
| Zomato API (live) | **MockProvider (default)** | The public Zomato Developer API shut down — the portal redirects to a POS integration product. `ZomatoProvider` is fully coded against the archived OpenAPI spec and tested via `Http::fake()`. `RESTAURANT_PROVIDER=mock` is the default so tests and CI never make real HTTP calls; switch to `zomato` if/when the API returns. |
| Vite (unspecified) | **Vite 8** | Latest stable at project start; required by `@tailwindcss/vite` 4. |

---

## License

MIT — see `"license": "MIT"` in [`composer.json`](composer.json).

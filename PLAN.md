# Telegram Culinary Bot API — Implementation Plan

Locked after extensive design-grilling. All decisions resolved. Ready to execute.

## Stack (deviations from brief noted; rationale in README)

- **Laravel 13.x** (released 2026-03-17) — brief said 11, updated for currency + package compatibility
- **PHP 8.3** minimum
- **Pest 4.x** — brief said 3
- **Larastan 3.9.x at level 8** — brief said level 6
- **Passport 13.7.x** (satisfies "JWT, Passport, dsb." + issues JWT access tokens)
- **google2fa-laravel 3.0.x** + **firebase/php-jwt** (for 2FA challenge token — separate from Passport)
- **spatie/laravel-query-builder 7.1.x**
- **dedoc/scramble** — auto-generated OpenAPI + Swagger UI at `/docs/api`
- **Postgres 17** (Railway managed plugin; brief said 16)
- **Redis 7** (Railway managed plugin)
- **FrankenPHP** worker mode
- **React 19 + TypeScript + Vite 6 + TanStack Router + TanStack Query + shadcn/ui + Tailwind 4** at `resources/js/admin/`
- **Pint** for formatting
- **Biome** for frontend lint/format

## Hosting & deploy

- **Railway** project (3 services: `web`, `worker`, `scheduler`)
- **Cloudflare subdomain** `laravel.catatkeu.app` → CNAME to Railway (orange-cloud proxy, Full Strict TLS)
- **GitHub Actions** for CI (lint/static/pest/newman/vitest); Railway native GitHub integration for deploy
- User manually adds CNAME in Cloudflare Dashboard
- `scripts/railway-setup.sh` (gitignored) runs `railway variables --set ...` once to seed env vars

## Auth flow (criterion a)

1. `POST /api/register` → Passport access token, 2FA not yet enabled
2. `POST /api/2fa/enable` → returns `{ otpauth_url }`; SPA renders QR client-side via `qrcode.react`
3. `POST /api/2fa/confirm { code }` → flips `two_factor_enabled = true`, generates 8 bcrypt-hashed recovery codes, returns them plaintext once
4. `POST /api/login { email, password }`:
   - If 2FA off → full Passport access token
   - If 2FA on → short-lived `challenge_token` (separate signed JWT, **not** a Passport token, 5-min TTL, signed with `JWT_CHALLENGE_SECRET`). Cannot reach any `auth:api` route.
5. `POST /api/2fa/verify { challenge_token, code }` → validates JWT + TOTP (or recovery code), issues real Passport access token
6. `Require2FA` middleware rejects Passport tokens when user has 2FA enabled but didn't pass verify step (tracked via `two_factor_confirmed_at` column set on verify success)
7. `throttle:6,1` on login + 2fa/verify
8. Password policy: `Password::defaults()` with mixedCase + letters + numbers, min 8

## Data layer (criterion b)

- All persistent data in **PostgreSQL**
- `users`, `restaurants`, `reviews`, `menu_items`, `api_logs`, `telegram_users`, `user_submissions`, `oauth_*`
- **Write-through caching**: every `ZomatoProvider` response upserts into Postgres mirror + Redis hot cache
- Cache TTLs per brief: 24h statics, 5min search, 1h details
- Cache keys `zomato:<endpoint>:<params>`, TTL-only invalidation
- `RestaurantRepository` owns cache logic; `RestaurantProvider` (Strategy) stays pure

## Restaurant domain (Phase 3)

- **Zomato API dead** (confirmed — developers.zomato.com redirects to POS-only integration). Strategy:
  - `ZomatoProvider` implements SwaggerHub spec 1:1, wired but not invoked in tests
  - `MockProvider` reads `tests/Fixtures/zomato/*.json` (payloads match Zomato schema exactly)
  - Config binding via `RESTAURANT_PROVIDER=mock|zomato`, default `mock`
  - Tests use `Http::fake()` against `developers.zomato.com/api/v2.1/*` URLs
- Fixtures: 5+ restaurants, 1 menu, 3 reviews, 1 geocode response
- Endpoints: `GET /api/restaurants?q=`, `/api/restaurants/{id}`, `/api/restaurants/{id}/reviews`, `/api/restaurants/nearby?lat=&lon=`
- Rate-limit guard: Redis counter, fail-open when 1000/day cap hit

## Telegram bot (Phase 4)

- `POST /api/telegram/webhook` protected by `ValidateTelegramSecret` middleware (constant-time compare on `X-Telegram-Bot-Api-Secret-Token`)
- Controller returns `response('', 200)` immediately, dispatches `ProcessTelegramUpdate` to Redis queue
- `MessageDispatcher` inspects update in deterministic order (callback, location, contact, video, photo, text)
- Handlers: `TextHandler`, `LocationHandler`, `ContactHandler`, `VideoHandler`, `PhotoHandler`, `CallbackHandler`
- `CommandRegistry` (Strategy) maps `/start`, `/search`, `/link`, `/help` to command classes; regex parse
- **User linking**: `GET /api/telegram/link-code` returns 6-digit code (10-min TTL); user sends `/link 847293` to bot; creates `TelegramUser` row binding `chat_id` ↔ `user_id`
- Callback `data` format: compact URL-encoded (`menu:12345`, `rev:12345:p2`)
- Photos/videos: store `file_id` only; OCR stub job calls `getFile` on demand
- Artisan command: `telegram:set-webhook` (registers webhook with Telegram)
- `TelegramBotService` wraps `Http` facade with timeout/retry/exception mapping

## Logging + admin (criterion f)

- `api_logs` table: `method`, `path`, `ip`, `user_agent`, `user_id`, `headers` (jsonb), `body` (jsonb), `response_status`, `duration_ms`, `request_id` (ULID), `response_size_bytes`, `route_name`
- `LogApiRequest` middleware's own `terminate()` dispatches `LogApiRequestJob` to Redis
- Redaction: flat case-insensitive deny-list `['password', 'password_confirmation', 'current_password', 'token', 'secret', 'authorization', 'cookie', 'two_factor_secret', 'challenge_token', 'access_token', 'refresh_token']`, recursive walk
- Header logging: all except deny-list `['authorization', 'cookie', 'x-telegram-bot-api-secret-token', 'x-csrf-token', 'x-xsrf-token']`
- Multipart bodies: file fields → `{filename, size_bytes, mime_type}`, text fields verbatim (post-redaction)
- 30-day retention via daily-scheduled `logs:prune` Artisan command
- `GET /api/admin/api-logs` with `spatie/laravel-query-builder` filters + pagination
- `/admin/logs` React page with login, filter form, table, pagination
- `/admin/2fa/enroll` React page with QR display + recovery code reveal
- SPA auth: access token in React state + httpOnly refresh cookie (BFF pattern)

## Design patterns document (criterion d) — 9 patterns

| # | Pattern | Location |
|---|---|---|
| 1 | Repository | `app/Repositories/*` |
| 2 | Service | `app/Services/**` |
| 3 | Strategy | `RestaurantProvider`, `MessageHandler`, `CommandRegistry` |
| 4 | Observer | `ApiLogObserver` |
| 5 | Singleton | Service providers |
| 6 | Facade | `Http`, `Cache`, `DB` |
| 7 | Pipeline | Middleware chain |
| 8 | Adapter | `TelegramBotService` wraps `Http` facade |
| 9 | Command | Artisan commands (`telegram:set-webhook`, `logs:prune`) |

## CI/CD (criterion e)

- `.github/workflows/ci.yml` jobs (parallel where possible):
  - `lint` — `vendor/bin/pint --test`
  - `static` — `vendor/bin/phpstan analyse` at level 8
  - `test-php` — `vendor/bin/pest --min=70 --coverage-path=app/Services,app/Http/Controllers`
  - `test-frontend` — `npm run lint` + `npm run typecheck` + `npm run test` (vitest)
  - `postman` — Newman CLI against ephemeral app booted with Postgres/Redis service containers
  - `deploy-notify` — placeholder job that green-checks after all above pass on main; Railway native GH integration handles the actual deploy
- PHP 8.3 single cell, Node 20, Postgres 17, Redis 7
- Required GH secrets: `POSTMAN_API_KEY`, `ZOMATO_USER_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `SEED_ADMIN_PASSWORD`, `APP_KEY`, `JWT_CHALLENGE_SECRET`

## Postman (criterion c)

- Via `mcp__plugin_postman_postman__*` tools: create workspace + public collection `telegram-culinary-bot`
- Single `Development` env with vars: `base_url`, `admin_token`, `user_token`, `challenge_token`, `telegram_webhook_url`, `zomato_user_key`
- Every request has: status-code check, JSON-schema check, token auto-extraction
- Published docs URL in README
- Additionally: Scramble-generated Swagger UI at `/docs/api`

## Telegram message types handled (criterion g)

Text, location, contact, video, photo, plus `callback_query` routing

## Phase order

1. **Phase 0** — Environment check (PHP, Composer, Docker, Git, Railway CLI). Print API-keys reminder block.
2. **Phase 1** — Scaffold Laravel 13, install Passport, Pest 4, Scramble, spatie/laravel-query-builder, google2fa-laravel, firebase/php-jwt. Migrations for `users` (+ 2FA cols + `is_admin`). `.env.example`. Docker Compose (5 services). React SPA scaffold with Vite + TanStack + shadcn.
3. **Phase 2** — Auth + 2FA endpoints + middleware + feature tests
4. **Phase 3** — Restaurant domain (provider strategy, repository, service, cache-through, endpoints, fixtures, tests)
5. **Phase 4** — Telegram webhook + dispatcher + 6 handlers + `/link` flow + `TelegramBotService` + `telegram:set-webhook` command + tests
6. **Phase 5** — Logging middleware + `api_logs` table + redaction + admin endpoint + React admin pages + `logs:prune` command
7. **Phase 6** — CI workflow + Postman collection (via MCP) + Railway setup (I run `railway init` + `railway variables --set`) + Cloudflare CNAME (user does manually) + README + DESIGN_PATTERNS.md + Scramble docs wiring

After each phase: run full test suite, print summary, grill user with one design question before the next phase starts.

## Out of scope (unchanged from brief)

- Payment integration
- Real OCR (stub returns fixed message)
- Email verification
- HIBP password checking
- PR preview deploys

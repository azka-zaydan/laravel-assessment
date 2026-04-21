# Railway deployment

**Project URL:** https://railway.com/project/e44de794-88d6-4ee9-b70c-705841d2e90f

## One-time setup (interactive CLI + dashboard)

The Railway CLI's `add` subcommand requires an interactive auth handshake that
scripts can't complete non-interactively. Run these in your terminal:

```bash
# 1. Verify linked project (should print "laravel-assessment")
railway status

# 2. Add managed Postgres plugin (pick "Postgres" at the prompt)
railway add -d postgres

# 3. Add managed Redis plugin
railway add -d redis

# 4. Create the web service linked to the GitHub repo (auto-deploys on push to main)
railway add --service web --repo azka-zaydan/laravel-assessment
```

After those run:

```bash
# 5. Select the web service
railway service web

# 6. Seed all env vars from local .env
./scripts/railway-setup.sh
```

## Cloudflare DNS

1. Open Cloudflare dashboard → `catatkeu.app` zone → DNS records.
2. Add:
   - Type: `CNAME`
   - Name: `laravel`
   - Target: (the Railway-provided `*.up.railway.app` hostname from `railway domain` on the `web` service)
   - Proxy status: **Proxied** (orange cloud)
3. In Railway dashboard → `web` service → Settings → Domains, add `laravel.catatkeu.app` as a custom domain. Railway will issue a TLS cert; Cloudflare terminates TLS upstream.

## After deploy

```bash
# Register the webhook with Telegram
railway run php artisan telegram:set-webhook
```

## GitHub secrets (required for CI)

Set in `azka-zaydan/laravel-assessment` repo → Settings → Secrets and variables:

| Name | Value |
|------|-------|
| `JWT_CHALLENGE_SECRET` | From `.env` |
| `TELEGRAM_BOT_TOKEN` | From `.env` |
| `TELEGRAM_WEBHOOK_SECRET` | From `.env` |
| `SEED_ADMIN_PASSWORD` | Any strong string |
| `POSTMAN_API_KEY` | For Newman in CI (optional — job skips if missing) |

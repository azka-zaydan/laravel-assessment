#!/usr/bin/env bash
# Seeds Railway env vars for the `laravel-assessment` project.
# Idempotent — re-run safely; `variables --set` overwrites.
# Reads from local .env so secrets stay out of git.
#
# Prerequisite (one-time, manual in dashboard OR via interactive CLI):
#   1. `railway init -n laravel-assessment -w "Azka Rafif Zaydan's Projects"`
#      (already done — project ID e44de794-88d6-4ee9-b70c-705841d2e90f)
#   2. `railway add -d postgres` (interactive, pick Postgres)
#   3. `railway add -d redis`    (interactive, pick Redis)
#   4. `railway add --service web --repo azka-zaydan/laravel-assessment`
#      to wire GitHub auto-deploy.
#   5. Run this script with the `web` service selected:
#        `railway service web && ./scripts/railway-setup.sh`

set -euo pipefail

if [[ ! -f .env ]]; then
    echo "error: .env not found. Copy .env.example to .env and fill in secrets first."
    exit 1
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

echo "Seeding Railway variables for the current service..."

railway variables \
    --set "APP_NAME=${APP_NAME:-Culinary Bot API}" \
    --set "APP_ENV=production" \
    --set "APP_DEBUG=false" \
    --set "APP_URL=https://laravel.catatkeu.app" \
    --set "APP_KEY=${APP_KEY}" \
    --set "LOG_CHANNEL=stack" \
    --set "LOG_STACK=single" \
    --set "LOG_LEVEL=info" \
    --set "DB_CONNECTION=pgsql" \
    --set "DB_HOST=\${{ Postgres.PGHOST }}" \
    --set "DB_PORT=\${{ Postgres.PGPORT }}" \
    --set "DB_DATABASE=\${{ Postgres.PGDATABASE }}" \
    --set "DB_USERNAME=\${{ Postgres.PGUSER }}" \
    --set "DB_PASSWORD=\${{ Postgres.PGPASSWORD }}" \
    --set "SESSION_DRIVER=database" \
    --set "QUEUE_CONNECTION=redis" \
    --set "CACHE_STORE=redis" \
    --set "REDIS_CLIENT=phpredis" \
    --set "REDIS_HOST=\${{ Redis.REDISHOST }}" \
    --set "REDIS_PORT=\${{ Redis.REDISPORT }}" \
    --set "REDIS_PASSWORD=\${{ Redis.REDISPASSWORD }}" \
    --set "MAIL_MAILER=log" \
    --set "RESTAURANT_PROVIDER=${RESTAURANT_PROVIDER:-mock}" \
    --set "ZOMATO_BASE_URL=${ZOMATO_BASE_URL:-https://developers.zomato.com/api/v2.1}" \
    --set "ZOMATO_USER_KEY=${ZOMATO_USER_KEY:-}" \
    --set "TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}" \
    --set "TELEGRAM_WEBHOOK_SECRET=${TELEGRAM_WEBHOOK_SECRET}" \
    --set "TELEGRAM_WEBHOOK_URL=${TELEGRAM_WEBHOOK_URL}" \
    --set "TELEGRAM_BOT_API_BASE=${TELEGRAM_BOT_API_BASE:-https://api.telegram.org}" \
    --set "JWT_CHALLENGE_SECRET=${JWT_CHALLENGE_SECRET}" \
    --set "JWT_CHALLENGE_TTL_MINUTES=${JWT_CHALLENGE_TTL_MINUTES:-5}" \
    --set "SEED_ADMIN_EMAIL=${SEED_ADMIN_EMAIL:-admin@example.test}" \
    --set "SEED_ADMIN_PASSWORD=${SEED_ADMIN_PASSWORD:-password}" \
    --set "LOG_REDACT_KEYS=${LOG_REDACT_KEYS}" \
    --set "API_LOG_RETENTION_DAYS=${API_LOG_RETENTION_DAYS:-30}"

echo "✅ Railway variables set."
echo "   Deploy via: git push origin main (Railway native GitHub integration) OR \`railway up\`."

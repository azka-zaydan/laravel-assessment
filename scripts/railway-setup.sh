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

# Build --set arguments conditionally (skip empty values, which Railway rejects).
args=()
add_var() {
    local key=$1
    local value=$2
    if [[ -n "$value" ]]; then
        args+=(--set "${key}=${value}")
    fi
}

add_var APP_NAME "${APP_NAME:-Culinary Bot API}"
add_var APP_ENV production
add_var APP_DEBUG false
add_var APP_URL "https://laravel.catatkeu.app"
add_var APP_KEY "${APP_KEY}"
add_var LOG_CHANNEL stack
add_var LOG_STACK single
add_var LOG_LEVEL info
add_var DB_CONNECTION pgsql
add_var DB_HOST "\${{ Postgres.PGHOST }}"
add_var DB_PORT "\${{ Postgres.PGPORT }}"
add_var DB_DATABASE "\${{ Postgres.PGDATABASE }}"
add_var DB_USERNAME "\${{ Postgres.PGUSER }}"
add_var DB_PASSWORD "\${{ Postgres.PGPASSWORD }}"
add_var SESSION_DRIVER database
add_var QUEUE_CONNECTION redis
add_var CACHE_STORE redis
add_var REDIS_CLIENT phpredis
add_var REDIS_HOST "\${{ Redis.REDISHOST }}"
add_var REDIS_PORT "\${{ Redis.REDISPORT }}"
add_var REDIS_PASSWORD "\${{ Redis.REDISPASSWORD }}"
add_var MAIL_MAILER log
add_var RESTAURANT_PROVIDER "${RESTAURANT_PROVIDER:-mock}"
add_var ZOMATO_BASE_URL "${ZOMATO_BASE_URL:-https://developers.zomato.com/api/v2.1}"
add_var ZOMATO_USER_KEY "${ZOMATO_USER_KEY}"
add_var TELEGRAM_BOT_TOKEN "${TELEGRAM_BOT_TOKEN}"
add_var TELEGRAM_WEBHOOK_SECRET "${TELEGRAM_WEBHOOK_SECRET}"
add_var TELEGRAM_WEBHOOK_URL "${TELEGRAM_WEBHOOK_URL}"
add_var TELEGRAM_BOT_API_BASE "${TELEGRAM_BOT_API_BASE:-https://api.telegram.org}"
add_var JWT_CHALLENGE_SECRET "${JWT_CHALLENGE_SECRET}"
add_var JWT_CHALLENGE_TTL_MINUTES "${JWT_CHALLENGE_TTL_MINUTES:-5}"
add_var SEED_ADMIN_EMAIL "${SEED_ADMIN_EMAIL:-admin@example.test}"
add_var SEED_ADMIN_PASSWORD "${SEED_ADMIN_PASSWORD:-password}"
add_var LOG_REDACT_KEYS "${LOG_REDACT_KEYS}"
add_var API_LOG_RETENTION_DAYS "${API_LOG_RETENTION_DAYS:-30}"

railway variables "${args[@]}"

echo "✅ Railway variables set."
echo "   Deploy via: git push origin main (Railway native GitHub integration) OR \`railway up\`."

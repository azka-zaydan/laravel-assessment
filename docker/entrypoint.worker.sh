#!/usr/bin/env sh
# ============================================================
# entrypoint.worker.sh — queue + cron workers only
#
# Responsibilities:
#   1. Wait for Postgres (IPv4/IPv6-safe via PDO; Railway private net is v6).
#   2. php artisan config:cache   (cheap, gives workers cached container).
#
# Explicitly NOT here:
#   - route:cache   → workers never resolve HTTP routes.
#   - view:cache    → workers never render Blade.
#   - migrate       → migrations are owned by the web service. Running them
#                     in three racing services corrupts schema_migrations.
# ============================================================
set -e

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
TIMEOUT=120
ELAPSED=0

echo "[worker-entrypoint] Waiting for ${DB_HOST}:${DB_PORT} via PDO ..."
until php -r "
try {
    new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_TIMEOUT => 3]
    );
    exit(0);
} catch (Throwable \$e) { exit(1); }
" 2>/dev/null; do
    if [ "${ELAPSED}" -ge "${TIMEOUT}" ]; then
        echo "[worker-entrypoint] ERROR: timed out after ${TIMEOUT}s waiting for Postgres at ${DB_HOST}:${DB_PORT}" >&2
        exit 1
    fi
    sleep 2
    ELAPSED=$((ELAPSED + 2))
done
echo "[worker-entrypoint] Database reachable after ${ELAPSED}s."

if [ "${APP_ENV}" != "local" ]; then
    echo "[worker-entrypoint] Caching config ..."
    php artisan config:cache
fi

exec "$@"

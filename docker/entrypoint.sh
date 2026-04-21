#!/usr/bin/env sh
set -e

# ── Wait for Postgres to accept connections ───────────────────────────────────
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
TIMEOUT=60
ELAPSED=0

echo "[entrypoint] Waiting for ${DB_HOST}:${DB_PORT} ..."
until nc -z "${DB_HOST}" "${DB_PORT}" 2>/dev/null; do
    if [ "${ELAPSED}" -ge "${TIMEOUT}" ]; then
        echo "[entrypoint] ERROR: timed out after ${TIMEOUT}s waiting for ${DB_HOST}:${DB_PORT}" >&2
        exit 1
    fi
    sleep 1
    ELAPSED=$((ELAPSED + 1))
done
echo "[entrypoint] Database reachable after ${ELAPSED}s."

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
if [ "${APP_ENV}" != "local" ]; then
    echo "[entrypoint] Caching config ..."
    php artisan config:cache

    echo "[entrypoint] Caching routes ..."
    php artisan route:cache
fi

echo "[entrypoint] Running migrations ..."
php artisan migrate --force --graceful

# ── Hand off to the container CMD ─────────────────────────────────────────────
exec "$@"

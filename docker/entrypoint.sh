#!/usr/bin/env sh
set -e

# ── Wait for Postgres to accept connections ───────────────────────────────────
# Uses PHP's PDO (IPv4 + IPv6 compatible — required on Railway where the private
# network is IPv6-only and plain `nc -z` fails).
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
TIMEOUT=120
ELAPSED=0

echo "[entrypoint] Waiting for ${DB_HOST}:${DB_PORT} via PDO ..."
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
        echo "[entrypoint] ERROR: timed out after ${TIMEOUT}s waiting for Postgres at ${DB_HOST}:${DB_PORT}" >&2
        exit 1
    fi
    sleep 2
    ELAPSED=$((ELAPSED + 2))
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

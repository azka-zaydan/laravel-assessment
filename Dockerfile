# ============================================================
# Stage 1 — Composer dependencies (cached layer)
# ============================================================
FROM composer:2 AS composer

WORKDIR /app

# Copy only the files Composer needs first — maximises cache reuse.
COPY composer.json composer.lock ./

# Install production deps into /app/vendor (no scripts yet; artisan not present).
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Now copy the full application so we can run post-install scripts and
# generate an optimised classmap.
COPY artisan ./
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/ database/
COPY public/ public/
COPY resources/ resources/
COPY routes/ routes/
COPY storage/ storage/

RUN composer dump-autoload --optimize --no-dev

# ============================================================
# Stage 2 — Production image (FrankenPHP)
# ============================================================
FROM dunglas/frankenphp:latest-php8.3 AS final

# ── PHP extensions ────────────────────────────────────────────────────────────
# install-php-extensions is bundled with the dunglas/frankenphp image.
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    bcmath \
    intl \
    gd \
    opcache

# ── OPcache configuration ─────────────────────────────────────────────────────
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.save_comments=1"; \
    echo "opcache.fast_shutdown=1"; \
} > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

# ── Application code ──────────────────────────────────────────────────────────
COPY --from=composer /app/vendor ./vendor
COPY artisan composer.json composer.lock ./
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/ database/
COPY public/ public/
COPY resources/ resources/
COPY routes/ routes/
COPY storage/ storage/

# ── Docker support files ──────────────────────────────────────────────────────
COPY docker/ docker/
COPY Caddyfile /etc/frankenphp/Caddyfile

RUN chmod +x /app/docker/entrypoint.sh

# ── Permissions ───────────────────────────────────────────────────────────────
# FrankenPHP runs as www-data by default in the official image.
RUN chown -R www-data:www-data storage bootstrap/cache

# ── Environment defaults ──────────────────────────────────────────────────────
ENV APP_ENV=production \
    APP_DEBUG=false

EXPOSE 8000

ENTRYPOINT ["/app/docker/entrypoint.sh"]

# Default: serve with FrankenPHP worker mode.
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

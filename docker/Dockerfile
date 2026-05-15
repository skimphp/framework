FROM php:8.5-cli-alpine

# Build deps — removed after extension compilation to keep image lean
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    curl \
    linux-headers \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    mysql-client \
    postgresql-client \
    nodejs \
    npm \
    $PHPIZE_DEPS

# Core extensions required by SKIM modules:
#   pdo_mysql / pdo_pgsql — DB module (both drivers supported)
#   mbstring              — str helpers, multibyte safety
#   intl                  — i18n module (symfony/translation)
#   zip                   — Composer installs, archive handling
#   bcmath                — precise math in financial/stats helpers
#   pcntl + posix         — queue worker signal handling (SIGTERM graceful stop)
#   sockets               — WebSocket module (amphp/websocket-server)
#   opcache               — enabled even in dev for realistic perf measurement
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    intl \
    zip \
    bcmath \
    pcntl \
    posix \
    sockets \
    opcache

# Redis — PECL (no Alpine package for php8.5-redis yet)
RUN pecl install redis && docker-php-ext-enable redis

# pcov — fast coverage collection (xdebug alternative, ~10× faster for CI)
# Only for dev/test — excluded from prod stage if you add one later
RUN pecl install pcov && docker-php-ext-enable pcov

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php.ini — dev config (opcache revalidate, error display, pcov settings)
COPY docker/php.ini /usr/local/etc/php/conf.d/skim.ini

WORKDIR /app

# Install PHP deps — mount vendor as volume in dev to avoid reinstall on rebuild
RUN composer install --no-interaction --prefer-dist --optimize-autoloader 2>/dev/null || true

EXPOSE 8080 5173

# php skim serve starts PHP built-in server + Vite together via proc_open
CMD ["php", "skim", "serve", "--host=0.0.0.0", "--port=8080"]

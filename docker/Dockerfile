# Using the stable 8.5 release
FROM php:8.5-cli-alpine

# Build deps
RUN apk add --no-cache \
    bash git unzip curl linux-headers \
    libzip-dev icu-dev icu-libs oniguruma-dev \
    libpq-dev postgresql-dev \
    mysql-client postgresql-client \
    nodejs npm \
    sqlite-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-configure pdo_pgsql --with-pdo-pgsql=/usr \
    && docker-php-ext-configure pdo_sqlite --with-pdo-sqlite=/usr \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        mbstring \
        intl \
        zip \
        bcmath \
        pcntl \
        posix \
        sockets

# Redis and pcov
RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install pcov && docker-php-ext-enable pcov

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php.ini — dev config
COPY docker/php.ini /usr/local/etc/php/conf.d/skim.ini

WORKDIR /app

# Create directories
RUN mkdir -p /app/storage /app/storage/pid /app/storage/logs && \
    chmod -R 777 /app/storage

# COPY source code BEFORE running composer install
COPY . /app

# Install PHP deps
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8080 5173

CMD ["php", "bin/skim", "serve", "--host=0.0.0.0", "--port=8080"]
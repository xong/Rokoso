# Produktions-Image: FrankenPHP (Caddy + PHP, MIT) mit automatischem HTTPS.
# Bauen:  docker build -t rokoso .
# Start:  siehe compose.prod.yaml und docs/BETRIEB.md
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions pdo_mysql intl zip gd opcache sodium mbstring iconv ctype

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:80

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-rokoso.ini

WORKDIR /app

# Abhängigkeiten zuerst (Layer-Cache)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console importmap:install \
    && php bin/console app:js:build --minify \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && mkdir -p var/storage public/uploads \
    && chown -R www-data:www-data var public/uploads

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/rokoso-entrypoint
COPY --chmod=755 docker/cron.sh /usr/local/bin/rokoso-cron

VOLUME ["/app/var/storage", "/app/public/uploads"]

ENTRYPOINT ["rokoso-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# syntax=docker/dockerfile:1.7
# LexLink production image (PHP 8.4-FPM). Stages:
#   base   - php-fpm + extensions
#   vendor - composer deps (no-dev, + faker for demo seeding)
#   assets - node 22 + php (Wayfinder vite plugin runs `php artisan wayfinder:generate`)
#   app    - final runtime image (app / horizon / reverb / scheduler)
#   nginx  - nginx with public/ baked in

FROM php:8.4-fpm-bookworm AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis gd intl bcmath pcntl zip opcache \
 && apt-get update && apt-get install -y --no-install-recommends libfcgi-bin unzip git \
 && rm -rf /var/lib/apt/lists/*
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini  $PHP_INI_DIR/conf.d/zz-lexlink.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-lexlink.conf
WORKDIR /var/www/html

FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress \
 # Factories/seeders need Faker for the demo `--fresh-seed`; it's a dev dep, so add it to the image only.
 && (composer show fakerphp/faker >/dev/null 2>&1 || composer require fakerphp/faker:^1.24 --update-no-dev --no-scripts --no-interaction --no-progress)
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && php artisan package:discover --ansi

FROM base AS assets
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /var/www/html /var/www/html
ARG VITE_APP_NAME=LexLink
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_APP_NAME=$VITE_APP_NAME VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST VITE_REVERB_PORT=$VITE_REVERB_PORT VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm run build

FROM base AS app
COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build
RUN rm -f public/hot && ln -sfn ../storage/app/public public/storage \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/lexlink-entrypoint
RUN chmod +x /usr/local/bin/lexlink-entrypoint
ENTRYPOINT ["lexlink-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

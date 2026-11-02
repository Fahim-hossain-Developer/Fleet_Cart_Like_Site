# syntax=docker/dockerfile:1
FROM composer:2 AS composer
FROM node:24-bookworm-slim AS node
FROM php:8.3-fpm-bookworm
RUN --mount=type=secret,id=proxy_ca,target=/run/proxy-ca.pem,required=true \
    export CURL_CA_BUNDLE=/run/proxy-ca.pem SSL_CERT_FILE=/run/proxy-ca.pem; \
    apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libavif-dev libonig-dev libsqlite3-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j2 bcmath intl zip gd pdo_mysql pdo_sqlite mbstring exif pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

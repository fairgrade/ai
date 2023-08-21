ARG PHP_VERSION=8.2
FROM makasim/nginx-php-fpm:${PHP_VERSION}-all-exts

ARG PHP_VERSION

## libs
RUN apt-get update && \
    apt-get install -y --no-install-recommends --no-install-suggests \
        php${PHP_VERSION}-dev \
        php${PHP_VERSION}-amqp \
        php${PHP_VERSION}-mysql \
    && \
    update-alternatives --install /usr/bin/php php /usr/bin/php${PHP_VERSION} 100

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /fairgrade

# Install vendor
COPY composer.json composer.lock .
RUN composer install
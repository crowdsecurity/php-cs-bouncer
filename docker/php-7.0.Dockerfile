FROM php:7.0-cli-alpine

RUN apk update \
    && apk add --no-cache git mysql-client curl libmcrypt libmcrypt-dev openssh-client icu-dev \
    libxml2-dev freetype-dev libpng-dev libjpeg-turbo-dev g++ make autoconf libmemcached-dev \
    && docker-php-source extract \
    && pecl install redis memcached \
    && docker-php-ext-enable redis memcached opcache \
    && docker-php-source delete \
    && docker-php-ext-install pdo_mysql \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /tmp/*

COPY docker/php-7.1.opcache.ini /usr/local/etc/php/conf.d/opcache.ini

ADD ./ /app
WORKDIR /app

EXPOSE 9000
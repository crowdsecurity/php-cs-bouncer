FROM php:7.3-cli-alpine

RUN apk update \
    && apk add --no-cache git=2.26.2-r0 mysql-client=10.4.15-r0 curl=7.69.1-r1 libmcrypt=2.5.8-r8 libmcrypt-dev=2.5.8-r8 openssh-client=8.3_p1-r0 icu-dev=67.1-r0 \
    libxml2-dev=2.9.10-r5 freetype-dev=2.10.4-r0 libpng-dev=1.6.37-r1 libjpeg-turbo-dev=2.0.5-r0 g++=9.3.0-r2 make=4.3-r0 autoconf=2.69-r2 libmemcached-dev=1.0.18-r4 \
    && docker-php-source extract \
    && pecl install xdebug redis memcached \
    && docker-php-ext-enable xdebug redis memcached \
    && docker-php-source delete \
    && docker-php-ext-install pdo_mysql \
    && echo "xdebug.remote_enable=on" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_autostart=off" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_port=9001" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_handler=dbgp" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_connect_back=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=mertblog.net" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_host=docker.for.mac.localhost" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /tmp/*

ADD ./ /app
WORKDIR /app

EXPOSE 9000
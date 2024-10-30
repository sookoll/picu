FROM php:8.3-fpm
ARG IMAGICK_VERSION=3.7.0

RUN apt update \
    && apt install -y zlib1g-dev libicu-dev libzip-dev libcurl3-dev libpng-dev libonig-dev libxml2-dev zip curl wget \
    && docker-php-ext-install intl opcache curl gd mbstring mysqli pdo pdo_mysql xml bcmath \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

RUN apt install -y libmagickwand-dev

RUN curl -L -o /tmp/imagick.tar.gz https://github.com/Imagick/imagick/archive/refs/tags/${IMAGICK_VERSION}.tar.gz \
    && tar --strip-components=1 -xf /tmp/imagick.tar.gz \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && echo "extension=imagick.so" > /usr/local/etc/php/conf.d/ext-imagick.ini \
    && rm -rf /tmp/*
    # <<< End of Imagick installation

# RUN pecl install imagick
# RUN docker-php-ext-enable imagick
RUN apt purge -y libmagickwand-dev

WORKDIR /var/www/picu

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

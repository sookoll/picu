FROM php:8.3-apache-bookworm

# Surpresses debconf complaints of trying to install apt packages interactively
# https://github.com/moby/moby/issues/4032#issuecomment-192327844

ARG DEBIAN_FRONTEND=noninteractive

# Install useful tools and install important libaries
RUN apt-get -y update && \
    apt-get upgrade -y && \
    apt-get -y --no-install-recommends install --fix-missing \
        apt-utils \
        build-essential \
        git \
        curl \
        nano \
        wget \
        dialog \
        libsqlite3-dev \
        libsqlite3-0 \
        default-mysql-client \
        zlib1g-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        iputils-ping \
        libcurl4 \
        libcurl4-openssl-dev \
        zip \
        openssl \
        libmagickwand-dev && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imagick Commit to install
# https://github.com/Imagick/imagick
ARG IMAGICK_COMMIT="28f27044e435a2b203e32675e942eb8de620ee58"

RUN cd /usr/local/src && \
    git clone https://github.com/Imagick/imagick && \
    cd imagick && \
    git checkout ${IMAGICK_COMMIT} && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    cd .. && \
    rm -rf imagick && \
    docker-php-ext-enable imagick

# Cleanup
RUN rm -rf /usr/local/src/*

# Other PHP8 Extensions
RUN docker-php-ext-install pdo_mysql && \
    docker-php-ext-install pdo_sqlite && \
    docker-php-ext-install bcmath && \
    docker-php-ext-install mysqli && \
    docker-php-ext-install curl && \
    docker-php-ext-install zip && \
    docker-php-ext-install -j$(nproc) intl && \
    docker-php-ext-install mbstring && \
    docker-php-ext-install gettext && \
    docker-php-ext-install calendar && \
    docker-php-ext-install exif


# Install Freetype
RUN apt-get -y update && \
    apt-get --no-install-recommends install -y libfreetype6-dev \
libjpeg62-turbo-dev \
libpng-dev && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd

# Insure an SSL directory exists
RUN mkdir -p /etc/apache2/ssl

# Enable SSL support
RUN a2enmod ssl && a2enmod rewrite

# Enable apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy Apache virtual host config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Copy PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

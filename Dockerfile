FROM php:8.4-fpm

ARG user=www
ARG uid=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libgmp-dev \
    libsodium-dev \
    zip \
    unzip \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd \
        intl \
        zip \
        pcntl \
        opcache \
        sockets \
        exif \
        gmp \
        sodium \
        mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Create system user and set permissions
RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user \
    && mkdir -p /var/www \
    && chown -R $user:$user /var/www

WORKDIR /var/www

USER $user

EXPOSE 9000

CMD ["php-fpm"]

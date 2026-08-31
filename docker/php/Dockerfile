FROM php:8.5-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid

# Install main dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    ffmpeg libsm6 libxext6 \
    libicu-dev \
    zlib1g-dev \
    libxml2-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libpng-dev \
    libxpm-dev \
    locales \
    pkg-config \
    sqlite3 libsqlite3-dev \
    libzip-dev zip unzip \
    git \
    curl \
    wget \
    gnupg

# Install PECL and PEAR extensions
RUN pecl install \
    redis

# Enable PECL and PEAR extensions
RUN docker-php-ext-enable \
    redis

# Configure php extensions
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp

# Install php extensions
RUN docker-php-ext-install gd
RUN docker-php-ext-install bcmath
RUN docker-php-ext-install exif
RUN docker-php-ext-install intl
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install pdo
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install pdo_sqlite
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install xml
RUN docker-php-ext-install zip

# Copy php.ini configurations
COPY php.ini /usr/local/etc/php/conf.d/40-custom.ini
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Cleanup dev dependencies
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && docker-php-source delete

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www

USER $user

EXPOSE 9000

# The main purpose of a CMD is to provide defaults for an executing container. These defaults can include an executable,
# or they can omit the executable, in which case you must specify an ENTRYPOINT instruction as well.
CMD php-fpm

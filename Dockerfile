FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
&& apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Install PHP dependencies (including dev — this is a dev container)
RUN composer install --optimize-autoloader --no-interaction

# Assets are pre-built locally and committed to git (public/build)
# No npm build needed on the server

EXPOSE 8000

CMD chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && php artisan package:discover --ansi \
    && php artisan migrate --force \
    && php artisan serve --host=0.0.0.0 --port=8000

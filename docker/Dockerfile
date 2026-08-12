FROM php:8.3-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy only composer files first (for layer caching)
COPY composer.json composer.lock ./

# Install dependencies into /var/www/vendor
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy rest of project
COPY . .

# Run post-install scripts now that full project is copied
RUN composer run-script post-autoload-dump || true

EXPOSE 9000
CMD ["php-fpm"]

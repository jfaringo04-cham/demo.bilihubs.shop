FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    && docker-php-ext-install pdo_mysql pdo pdo_pgsql zip gd mbstring dom ctype

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first (para may cache layer)
COPY composer.json composer.lock ./

# Install PHP dependencies (skip scripts to avoid artisan errors during build)
RUN composer install --no-interaction --no-scripts --optimize-autoloader --no-dev

# Copy the rest of the project
COPY . .

# Set permissions for storage and bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Build frontend assets (optional, skip if package.json or npm unavailable)
RUN if [ -f package.json ] && [ -f /usr/bin/npm ]; then \
      npm install --no-audit --no-fund 2>/dev/null && \
      npm run build 2>/dev/null; \
    fi || true

# Run package discovery (needs full project files)
RUN composer dump-autoload --optimize

# Expose port
EXPOSE 8000

# Start Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

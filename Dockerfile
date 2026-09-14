FROM php:8.3-cli

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
    && docker-php-ext-install pdo_mysql zip gd mbstring dom ctype

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

# Build frontend assets (optional, skip if package.json missing)
RUN if [ -f package.json ]; then \
      npm install --no-audit --no-fund 2>/dev/null && \
      npm run build 2>/dev/null; \
    fi || true

# Run package discovery (needs full project files)
RUN composer dump-autoload --optimize

# Expose port
EXPOSE 8000

# Start Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

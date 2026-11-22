FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl gd bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY backend/ .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && rm -rf public/storage \
    && ln -s /app/storage/app/public public/storage

EXPOSE 8080

CMD php -d memory_limit=512M artisan migrate --force && php -d memory_limit=512M artisan db:seed --class=RolesAndPermissionsSeeder --force && php -d memory_limit=512M artisan db:seed --class=PipelineStagesForAllCompaniesSeeder --force && (php artisan storage:link || true) && (while true; do php -d memory_limit=512M artisan queue:work --sleep=1 --tries=3; sleep 1; done &) && (php -d memory_limit=512M artisan schedule:work &) && php -d memory_limit=512M artisan serve --host=0.0.0.0 --port=${PORT:-8080}

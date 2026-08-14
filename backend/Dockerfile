FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY backend/ .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080
EXPOSE 8081

CMD php artisan migrate --force && php artisan db:seed --class=RolesAndPermissionsSeeder --force && php artisan db:seed --class=PipelineStagesForAllCompaniesSeeder --force && php artisan storage:link && (php artisan queue:work --sleep=1 --tries=3 &) && (php artisan schedule:work &) && (php artisan reverb:start --host=0.0.0.0 --port=${REVERB_SERVER_PORT:-8081} &) && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}

FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    nodejs npm ca-certificates \
    && docker-php-ext-install pdo_mysql zip gd bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'upload_max_filesize=10M\npost_max_size=64M\nmemory_limit=256M\nopcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=128\n' > /usr/local/etc/php/conf.d/zz-thriftverse.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN npm install && npm run build

ENV PHP_CLI_SERVER_WORKERS=4

CMD ["sh", "-c", "mkdir -p storage/app/public/listings; php artisan storage:link --force; php artisan view:cache; php artisan migrate --force; exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
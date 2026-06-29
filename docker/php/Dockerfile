FROM php:8.4-fpm-alpine

# System deps + PHP extensions needed by Laravel + image handling
RUN apk add --no-cache \
        git curl bash \
        libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        icu-dev oniguruma-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath intl exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Local dev only: Windows bind-mounted files are root-owned, so run the FPM
# workers as root to let Laravel write storage/ (views, logs, cache).
# Production uses proper ownership instead — see deploy config.
RUN sed -i 's/^user = www-data/user = root/; s/^group = www-data/group = root/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

CMD ["php-fpm", "--allow-to-run-as-root"]

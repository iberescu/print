FROM php:8.4-fpm-alpine

# System deps + PHP extensions needed by Laravel + image handling.
# mupdf-tools provides `mutool draw` — renders uploaded PDF artwork to images
# so the editor can show a real preview and let the user position pages.
RUN apk add --no-cache \
        git curl bash mupdf-tools \
        libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev \
        icu-dev oniguruma-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath intl exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Review posts a design JSON that may still carry image data-URLs (the editor
# offloads placed images to /design/asset in the background, but a buyer can hit
# Review before an offload finishes) — PHP's default 8M post cap silently drops
# such bodies. nginx already allows 64M.
RUN { \
        echo 'post_max_size = 48M'; \
        echo 'upload_max_filesize = 24M'; \
        echo 'memory_limit = 512M'; \
    } > /usr/local/etc/php/conf.d/zz-runmyprint.ini

# Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Local dev only: Windows bind-mounted files are root-owned, so run the FPM
# workers as root to let Laravel write storage/ (views, logs, cache).
# Production uses proper ownership instead — see deploy config.
RUN sed -i 's/^user = www-data/user = root/; s/^group = www-data/group = root/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

CMD ["php-fpm", "--allow-to-run-as-root"]

FROM php:8.4-apache-bookworm

# Расширения, нужные редактору сайта (WebP/GD) и mbstring для кириллицы.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd mbstring zip \
    && a2enmod rewrite headers expires deflate setenvif \
    && rm -rf /var/lib/apt/lists/*

COPY config/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY config/php.ini /usr/local/etc/php/php.ini
COPY bin/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY bin/make_config.php /usr/local/bin/make_config.php
COPY bin/render_index.php /usr/local/bin/render_index.php
COPY site/ /var/www/html/

RUN chmod 0755 /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 0755 {} + \
    && find /var/www/html -type f -exec chmod 0644 {} +

ENV PORT=10000 \
    BM_DISABLE_MAIL=1

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]

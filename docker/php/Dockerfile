FROM php:7.4-apache

ENV TZ=America/Santiago

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        cron \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-spa \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        zip \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/security.ini /usr/local/etc/php/conf.d/99-e-talent-security.ini
COPY docker/php/entrypoint.sh /usr/local/bin/e-talent-entrypoint
COPY docker/php/e-talent-cleanup.cron /etc/cron.d/e-talent-cleanup

RUN chmod 0644 /etc/cron.d/e-talent-cleanup \
    && chmod +x /usr/local/bin/e-talent-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["e-talent-entrypoint"]
CMD ["apache2-foreground"]

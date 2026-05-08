FROM php:8.2-apache

RUN apt-get update --fix-missing && apt-get install -y --no-install-recommends \
    git zip unzip \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    xml \
    mbstring \
    curl \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes +FollowSymLinks\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

WORKDIR /var/www/html

RUN touch /var/www/html/.env

RUN composer install --no-dev --optimize-autoloader --no-scripts

RUN mkdir -p /var/www/html/var/cache/prod/sessions \
    && mkdir -p /var/www/html/var/log \
    && chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
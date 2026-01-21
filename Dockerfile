FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    libpq-dev \
    libcurl4-openssl-dev \
 && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    curl \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
    
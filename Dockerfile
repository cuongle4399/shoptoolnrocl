FROM php:8.2-apache

# Cài system dependencies cần thiết
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    libpq-dev \
    libcurl4-openssl-dev \
    git \
    unzip \
 && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    curl \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# 👉 Cài Composer (QUAN TRỌNG)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set thư mục làm việc
WORKDIR /var/www/html

# Copy source code
COPY . .

# 👉 Cài vendor (Google Client Library nằm ở đây)
RUN composer install --no-dev --optimize-autoloader

# Set quyền cho Apache
RUN chown -R www-data:www-data /var/www/html

# Entry point
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

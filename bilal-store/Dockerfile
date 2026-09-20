FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader


FROM php:8.2-apache

# Install required PHP extensions for MySQL/PDO
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Copy application
COPY . /var/www/html/

# Copy Composer dependencies from builder stage
COPY --from=composer /app/vendor /var/www/html/vendor

# Allow Apache to access the application files
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
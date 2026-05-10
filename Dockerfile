
FROM php:8.1-apache

# Install system dependencies and required extensions
RUN apt-get update && apt-get install -y \
    unzip git curl \
    libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files and install dependencies
WORKDIR /var/www/html
COPY html/composer.json ./
RUN composer install --no-interaction --no-scripts

# Enable mod_rewrite
RUN a2enmod rewrite

# Set ServerName to suppress warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Increase upload limits for KYC document uploads
RUN printf 'upload_max_filesize = 100M\npost_max_size = 100M\n' > /usr/local/etc/php/conf.d/uploads.ini

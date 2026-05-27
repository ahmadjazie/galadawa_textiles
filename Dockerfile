FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

# Set Apache document root correctly
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Update Apache config to allow access
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy project files
COPY . /var/www/html/

# Fix permissions (IMPORTANT)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Ensure uploads folder exists
RUN mkdir -p /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads

EXPOSE 80
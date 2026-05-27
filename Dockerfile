FROM php:8.2-apache

# Enable rewrite module (important for PHP apps)
RUN a2enmod rewrite

# Install MySQL support
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project into Apache folder
COPY . /var/www/html/

# Fix permissions (VERY IMPORTANT on Render)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create uploads folder safely
RUN mkdir -p /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads

EXPOSE 80
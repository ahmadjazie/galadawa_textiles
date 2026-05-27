FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

RUN { \
        echo "upload_max_filesize=10M"; \
        echo "post_max_size=256M"; \
        echo "max_file_uploads=300"; \
    } > /usr/local/etc/php/conf.d/galadawa-uploads.ini

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80

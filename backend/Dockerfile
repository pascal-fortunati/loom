FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

WORKDIR /var/www/html
COPY . /var/www/html/

RUN printf '%s\n' 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && printf '%s\n' 'SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1' > /etc/apache2/conf-available/auth-header.conf \
    && a2enconf auth-header

RUN mkdir -p uploads/avatars uploads/posts tmp/ratelimit \
    && chown -R www-data:www-data uploads tmp \
    && chmod -R 775 uploads tmp

EXPOSE 80

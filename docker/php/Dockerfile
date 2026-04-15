FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

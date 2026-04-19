FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron mariadb-client \
    && docker-php-ext-install mysqli pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY cron/crontabs/root /etc/cron.d/root
RUN chmod 0644 /etc/cron.d/root

WORKDIR /var/www/html

CMD ["cron", "-f"]

FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron \
    && docker-php-ext-install mysqli pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY docker/cron/graymentality.cron /etc/cron.d/graymentality
RUN chmod 0644 /etc/cron.d/graymentality

WORKDIR /var/www/html

CMD ["cron", "-f"]

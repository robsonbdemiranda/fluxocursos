# syntax=docker/dockerfile:1
FROM php:8.4-apache

ARG PHPMAILER_COMMIT=1bc1716a507a65e039d4ac9d9adebbbd0d346e15

RUN apt-get update \
    && apt-get install --no-install-recommends --yes git \
    && rm -rf /var/lib/apt/lists/* \
    && git clone --depth 1 --branch v7.1.1 https://github.com/PHPMailer/PHPMailer.git /tmp/phpmailer \
    && test "$(git -C /tmp/phpmailer rev-parse HEAD)" = "$PHPMAILER_COMMIT" \
    && mkdir -p /var/www/html/vendor/phpmailer \
    && mv /tmp/phpmailer /var/www/html/vendor/phpmailer/phpmailer \
    && a2enmod headers rewrite

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

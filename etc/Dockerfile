FROM php:8.1.14-cli-bullseye

RUN docker-php-ext-install pcntl

COPY eqivo.phar /usr/local/bin/eqivo

ENTRYPOINT ["/usr/local/bin/eqivo"]

FROM php:8.3.0-cli-bookworm

RUN docker-php-ext-install pcntl

COPY eqivo.phar /usr/local/bin/eqivo

ENTRYPOINT ["/usr/local/bin/eqivo"]

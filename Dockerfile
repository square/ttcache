FROM php:8.2-cli
RUN apt-get update && apt-get install -y libz-dev libmemcached-dev zlib1g-dev libssl-dev
RUN pecl install memcached
RUN docker-php-ext-enable memcached

FROM php:7.4.27-cli
RUN apt-get update && apt-get install -y libmemcached-dev zlib1g-dev \
    && pecl install memcached-3.1.5 \
    && docker-php-ext-enable memcached \
    && pecl install redis-5.3.7 \
    && docker-php-ext-enable redis

FROM php:7-apache

ENV DEBIAN_FRONTEND noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /tmp/

RUN apt-get update && apt-get install -y --no-install-recommends \
	    git \
        zlib1g-dev \
	&& rm -r /var/lib/apt/lists/* \
	&& docker-php-ext-install -j$(nproc) zip \
	&& curl -sS --fail https://getcomposer.org/installer | php \
	&& mv /tmp/composer.phar /usr/local/bin/composer 

COPY . /appcode/
WORKDIR /appcode/
RUN composer install --no-interaction
CMD ["/appcode/run.sh"]

FROM php:7

COPY . /tmp/
WORKDIR /tmp/

RUN apt-get update && apt-get install -y --no-install-recommends \
	    git \
        zlib1g-dev \
	&& rm -r /var/lib/apt/lists/* \
	&& docker-php-ext-install -j$(nproc) zip \
	&& curl -sS --fail https://getcomposer.org/installer | php \
	&& mv /tmp/composer.phar /usr/local/bin/composer 

RUN composer install --no-interaction
ENTRYPOINT php main.php

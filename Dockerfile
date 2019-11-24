FROM php:7-apache

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /code

RUN apt-get update && apt-get install -y \
	    git \
        libzip-dev \
        unzip \
  	--no-install-recommends && rm -r /var/lib/apt/lists/* \
	&& docker-php-ext-install zip

COPY ./docker/php/php.ini /usr/local/etc/php/php.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

COPY composer.* ./
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader
COPY . .
RUN composer install $COMPOSER_FLAGS

ENV DL_USER=dl-user

RUN useradd $DL_USER
# add user to the users group (GID 100)
RUN usermod -a -G users $DL_USER
RUN mkdir /home/$DL_USER
RUN chown $DL_USER /home/$DL_USER

RUN chgrp -R users tests/functional

USER root

CMD ["/code/run.sh"]

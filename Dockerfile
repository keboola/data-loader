FROM php:7

COPY . /tmp/

WORKDIR /tmp/

RUN apt-get update && apt-get install -y --no-install-recommends \
	git && \
	rm -r /var/lib/apt/lists/* && \
	curl -sS --fail https://getcomposer.org/installer | php && \
	mv /tmp/composer.phar /usr/local/bin/composer && \
	curl -sS --fail https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.4.0.phar > /usr/local/bin/sapi-client.phar

#ENTRYPOINT php /usr/local/bin/sapi-client.phar --token=$KBC_TOKEN export-table in.c-application-testing.carseats /data/table.csv
ENTRYPOINT php main.php

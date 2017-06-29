#!/bin/bash
set -e

echo "Starting tests" >&1
php --version \
	&& composer --version \
 	&& /code/vendor/bin/phpcs --standard=psr2 -n --ignore=vendor --extensions=php . \

export KBC_EXPORT_CONFIG="{\"storage\":{\"input\":{\"tables\":[{\"source\":\"in.c-main.source\",\"destination\":\"destination.csv\"}]}}}"
file="/data/in/tables/destination.csv"

php /code/Tests/sapi-client.phar create-table --token=$KBC_TOKEN "in.c-main" "source" /code/Tests/source.csv
php /code/main.php
php /code/Tests/sapi-client.phar delete-table --token=$KBC_TOKEN "in.c-main.source"

if [ -f "$file" ]
then
	echo "$file found." >&1
	exit 0
else
	echo "$file not found." >&2
	exit 1
fi

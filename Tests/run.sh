#!/bin/bash
set -e

echo "Starting tests" >&1
php --version \
	&& composer --version \
 	&& /code/vendor/bin/phpcs --standard=psr2 -n --ignore=vendor --extensions=php /code/

export KBC_EXPORT_CONFIG="{\"storage\":{\"input\":{\"tables\":[{\"source\":\"in.c-main.source\",\"destination\":\"destination.csv\"}]}}}"
file="/data/in/tables/destination.csv"

curl -sS --fail https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.phar --output /code/Tests/sapi-client.phar
php /code/Tests/sapi-client.phar purge-project --token=${KBC_TOKEN}
php /code/Tests/sapi-client.phar create-bucket --token=${KBC_TOKEN} "in" "main"
php /code/Tests/sapi-client.phar create-table --token=${KBC_TOKEN} "in.c-main" "source" /code/Tests/source.csv
php /code/main.php
php /code/Tests/sapi-client.phar delete-bucket --token=${KBC_TOKEN} "in.c-main" --recursive

if [ -f "$file" ]
then
	echo "$file found." >&1
else
	echo "$file not found." >&2
	exit 1
fi

export KBC_EXPORT_CONFIG="{\"storage\":{\"input\":{\"tables\":[{\"invalid\":\"configuration\"}]}}}"
set +e
php /code/main.php
if [ "$?" -eq 1 ]; then
	echo "Exit code correct" >&1
else
	echo "Exit code incorrect" >&2
	exit 1
fi
set -e

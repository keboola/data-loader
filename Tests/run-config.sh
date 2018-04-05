#!/bin/bash
set -e

printf "Starting tests\n" >&1
php --version \
	&& composer --version \
 	&& /code/vendor/bin/phpcs --standard=psr2 -n --ignore=vendor --extensions=php /code/

curl -sS --fail https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.phar --output /code/Tests/sapi-client.phar
# Create bucket and table
php /code/Tests/sapi-client.phar purge-project --token=${KBC_TOKEN}
php /code/Tests/sapi-client.phar create-bucket --token=${KBC_TOKEN} "in" "main"
php /code/Tests/sapi-client.phar create-table --token=${KBC_TOKEN} "in.c-main" "source" /code/Tests/files/source.csv
php /code/Tests/uploadFiles.php

# Create configuration
export KBC_CONFIG_VERSION=2
COMMAND_RESULT=$(curl -sS --fail -X POST \
  {$KBC_STORAGEAPI_URL}/v2/storage/components/transformation/configs \
  -H "content-type: application/x-www-form-urlencoded" \
  -H "x-storageapi-token: ${KBC_TOKEN}" \
  -d "name=test")
export KBC_CONFIG_ID=$(echo ${COMMAND_RESULT} | grep -o '"id":"[0-9]*"' | grep -o '[0-9]*')
printf "Configuration ID: ${KBC_CONFIG_ID}\n"
printf "Creating configuration row\n" >&1
CONFIG_DATA=$(cat /code/Tests/sample-config-tables.json)
COMMAND_RESULT=$(curl -sS --fail -X POST \
  {$KBC_STORAGEAPI_URL}/v2/storage/components/transformation/configs/${KBC_CONFIG_ID}/rows \
  -H "content-type: application/x-www-form-urlencoded" \
  -H "x-storageapi-token: ${KBC_TOKEN}" \
  -d "configuration=${CONFIG_DATA}")
export KBC_ROW_ID=$(echo ${COMMAND_RESULT} | grep -o 'Row [0-9]* added' | grep -o '[0-9]*')
printf "Configuration row: ${KBC_ROW_ID}\n"

php /code/main.php

# Check the results
file="/data/in/tables/destination.csv"

if [ -f "$file" ]
then
	printf "\nFile $file found.\n\n" >&1
	diff $file /code/Tests/files/source.csv
else
	printf "\nFile $file not found.\n\n" >&2
	exit 1
fi

file="/data/in/tables/filtered.csv"

if [ -f "$file" ]
then
	printf "\nFile $file found.\n\n" >&1
	diff $file /code/Tests/files/filtered.csv
else
	printf "\nFile $file not found.\n\n" >&2
	exit 1
fi


# Create configuration
export KBC_CONFIG_VERSION=2
COMMAND_RESULT=$(curl -sS --fail -X POST \
  {$KBC_STORAGEAPI_URL}/v2/storage/components/transformation/configs \
  -H "content-type: application/x-www-form-urlencoded" \
  -H "x-storageapi-token: ${KBC_TOKEN}" \
  -d "name=test")
export KBC_CONFIG_ID=$(echo ${COMMAND_RESULT} | grep -o '"id":"[0-9]*"' | grep -o '[0-9]*')
printf "Configuration ID: ${KBC_CONFIG_ID}\n"
printf "Creating configuration row\n" >&1
CONFIG_DATA=$(cat /code/Tests/sample-config-files.json)
COMMAND_RESULT=$(curl -sS --fail -X POST \
  {$KBC_STORAGEAPI_URL}/v2/storage/components/transformation/configs/${KBC_CONFIG_ID}/rows \
  -H "content-type: application/x-www-form-urlencoded" \
  -H "x-storageapi-token: ${KBC_TOKEN}" \
  -d "configuration=${CONFIG_DATA}")
export KBC_ROW_ID=$(echo ${COMMAND_RESULT} | grep -o 'Row [0-9]* added' | grep -o '[0-9]*')
printf "Configuration row: ${KBC_ROW_ID}\n"

php /code/main.php
# Check the results
fileCount=$(ls /data/in/files | grep -c '_dummy')

if [ "$fileCount" -eq 2 ]; then
  printf "\n2 Files found.\n\n" >&1
else
  printf "\n2 Files not found (found '$fileCount').\n\n" >&2
  exit 1
fi


# Check for non-existent configuration
export KBC_CONFIG_ID="non-existent-config"
export KBC_ROW_ID=1
export KBC_CONFIG_VERSION=1

set +e
php /code/main.php
if [ "$?" -eq 171 ]; then
	printf "\nExit code correct\n\n" >&1
else
	printf "\nExit code incorrect\n\n" >&2
	exit 1
fi
set -e

# Check for invalid configuration

# Create configuration
export KBC_CONFIG_ID="test-config-3"
export KBC_CONFIG_VERSION=2
COMMAND_RESULT=$(curl -sS --fail -X POST \
  {$KBC_STORAGEAPI_URL}/v2/storage/components/transformation/configs \
  -H "content-type: application/x-www-form-urlencoded" \
  -H "x-storageapi-token: ${KBC_TOKEN}" \
  -d "name=test")
export KBC_CONFIG_ID=$(echo ${COMMAND_RESULT} | grep -o '"id":"[0-9]*"' | grep -o '[0-9]*')
printf "Configuration ID: ${KBC_CONFIG_ID}\n"
printf "Creating configuration row\n" >&1
CONFIG_DATA=$(cat /code/Tests/sample-config-tables.json)
COMMAND_RESULT=$(curl -sS --fail -X POST \
  {$KBC_STORAGEAPI_URL}/v2/storage/components/transformation/configs/${KBC_CONFIG_ID}/rows \
  -H "content-type: application/x-www-form-urlencoded" \
  -H "x-storageapi-token: ${KBC_TOKEN}" \
  -d "configuration={\"a\": \"b\"}")
export KBC_ROW_ID=$(echo ${COMMAND_RESULT} | grep -o 'Row [0-9]* added' | grep -o '[0-9]*')
printf "Configuration row: ${KBC_ROW_ID}"
set +e
php /code/main.php
if [ "$?" -eq 171 ]; then
	printf "\nExit code correct\n\n" >&1
else
	printf "\nExit code incorrect\n\n" >&2
	exit 1
fi
set -e

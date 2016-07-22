#!/bin/bash

docker login -e="." -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/data-loader quay.io/keboola/data-loader:$TRAVIS_TAG
docker tag keboola/data-loader quay.io/keboola/data-loader:latest
docker images
docker push quay.io/keboola/data-loader:$TRAVIS_TAG
docker push quay.io/keboola/data-loader:latest

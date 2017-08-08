#!/bin/bash
set -e

# taken from https://gist.github.com/BretFisher/14cd228f0d7e40dae085
# install aws cli w/o sudo
pip install --user awscli
# put aws in the path
export PATH=$PATH:$HOME/.local/bin
# needs AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY envvars
eval $(aws ecr get-login --region us-east-1)
docker tag keboola/data-loader:latest 147946154733.dkr.ecr.us-east-1.amazonaws.com/keboola/data-loader:${TRAVIS_TAG}
docker tag keboola/data-loader:latest 147946154733.dkr.ecr.us-east-1.amazonaws.com/keboola/data-loader:latest
docker push 147946154733.dkr.ecr.us-east-1.amazonaws.com/keboola/data-loader:${TRAVIS_TAG}
docker push 147946154733.dkr.ecr.us-east-1.amazonaws.com/keboola/data-loader:latest
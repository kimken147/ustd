#!/usr/bin/env bash

set -e

sudo /usr/bin/aws s3 cp s3://${S3_BUCKET}/.env.${BRANCH} /var/app/staging/.env
sudo chmod 644 /var/app/staging/.env
sudo chown webapp:webapp /var/app/staging/.env


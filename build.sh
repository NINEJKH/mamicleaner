#!/usr/bin/env bash

set -e

if [[ ! -z "${TRAVIS_TAG}" ]]; then
  echo "${TRAVIS_TAG}" > version.txt
elif [[ ! -z "${TRAVIS_COMMIT}" ]]; then
  echo "${TRAVIS_COMMIT}" > version.txt
fi

php create-phar.php
chmod +x mamicleaner.phar

./mamicleaner.phar --version

#!/usr/bin/env bash

set -ex

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}

mkdir -p cov

WP_TESTS_DIR=$WP_TESTS_DIR phpunit --coverage-php cov/main.cov
WP_TESTS_DIR=$WP_TESTS_DIR PHPRC=ini_disable_functions phpunit --coverage-php cov/disable_functions.cov

wget https://phar.phpunit.de/phpcov.phar

php phpcov.phar merge cov --clover clover.xml && bash <(curl -s https://codecov.io/bash)

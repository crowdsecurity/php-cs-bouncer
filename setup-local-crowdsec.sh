#!/bin/sh

# Delete existing LAPI database.
[ -e ./var/docker-data/crowdsec.db ] && rm ./var/docker-data/crowdsec.db

# Start containers.
docker-compose up --force-recreate --remove-orphans -d crowdsec redis memcached

# Create a bouncer with cscli and copy expose generated key.
docker-compose exec crowdsec /usr/local/bin/cscli bouncers add bouncer-php-library -o raw > .bouncer-key

# Create a watcher with cscli.
docker-compose exec crowdsec cscli machines add PhpUnitTestMachine --password PhpUnitTestMachinePassword > /dev/null 2>&1
# TODO P3 try to use https://crowdsecurity.github.io/api_doc/?urls.primaryName=LAPI#/watchers/RegisterWatcher in PhpUnit instead.
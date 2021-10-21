#!/bin/sh

# Delete existing LAPI database.
[ -e ./var/docker-data/crowdsec.db ] && docker-compose exec crowdsec rm -f /var/lib/crowdsec/data/crowdsec.db

# Start containers.
docker-compose up --force-recreate -d crowdsec
docker-compose up --remove-orphans -d redis memcached

# Create a bouncer with cscli and copy expose generated key.
docker-compose exec crowdsec /usr/local/bin/cscli bouncers add bouncer-php-library -o raw > ./.bouncer-key

# Create a watcher with cscli.
docker-compose exec crowdsec cscli machines add PhpUnitTestMachine --password PhpUnitTestMachinePassword > /dev/null 2>&1

# Ensure composer deps are presents
docker-compose run app composer install
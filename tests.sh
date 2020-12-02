#!/bin/sh

#####################
### Setup context ###
#####################

[ -e ./var/docker-data/crowdsec.db ] && rm ./var/docker-data/crowdsec.db

docker-compose up --force-recreate --remove-orphans -d crowdsec composer redis memcached app

docker-compose exec crowdsec /usr/local/bin/cscli bouncers add bouncer-php-library -o raw > .bouncer-key

docker-compose exec crowdsec cscli machines add PhpUnitTestMachine --password PhpUnitTestMachinePassword > /dev/null 2>&1
# TODO P3 try to use https://crowdsecurity.github.io/api_doc/?urls.primaryName=LAPI#/watchers/RegisterWatcher in PhpUnit instead.

#######################
### Display context ###
#######################

#docker-compose exec crowdsec /usr/local/bin/cscli decisions list -o json | jq

#################
### Run tests ###
#################

docker-compose run --rm app ./vendor/bin/phpunit --testdox --colors --exclude-group ignore tests/IpVerificationTest.php

################
### Clean up ###
################

#docker-compose down
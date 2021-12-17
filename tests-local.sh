#!/bin/sh

CONTAINER_NAME=${1:-app}
# Setup local CrowdSec instance
./scripts/setup-local-crowdsec.sh

docker-compose run --rm $CONTAINER_NAME ./vendor/bin/phpunit --testdox --colors --exclude-group ignore tests/IpVerificationTest.php

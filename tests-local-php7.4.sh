#!/bin/sh

# Setup local CrowdSec instance
./scripts/setup-local-crowdsec.sh

docker-compose run --rm app-php7.4 ./vendor/bin/phpunit --testdox --colors --exclude-group ignore tests/IpVerificationTest.php
# PHP Bouncer Tutorial

This example demonstrates the features offered by [CrowdSec PHP Bouncer library](https://github.com/crowdsecurity/php-cs-bouncer).

For this demo, we will start from a minimum configuration and improve it step by step.

Let's get started and follow the guide!

### 1) Setup the context

Start the containers:

```bash
docker-compose run app composer install
./scripts/setup-local-crowdsec.sh
```

Then get a bouncer API key with:

```bash
export BOUNCER_API_KEY=`docker-compose exec crowdsec /usr/local/bin/cscli bouncers add example-bouncer-php-library -o raw`
```

### 2) Get the remediation the clean IP "1.2.3.4"

Try with the simple-example-live-mode.php file:

```bash
docker-compose run --rm app php ./examples/live-mode/simple-example-live-mode.php $BOUNCER_API_KEY 1.2.3.4 http://crowdsec:8080
```

This example was just to show you to fastest way to request an IP to LAPI with this library. But now let's use the full configured example :

```bash
docker-compose run --rm app php ./examples/live-mode/full-example-live-mode.php $BOUNCER_API_KEY 1.2.3.4 http://crowdsec:8080
```

### 3) Simulate LAPI down by stopping its container

```bash
docker-compose stop crowdsec
```

### 4) One more time, get the remediation the clean IP "1.2.3.4"

```bash
docker-compose run --rm app php ./examples/live-mode/full-example-live-mode.php $BOUNCER_API_KEY 1.2.3.4 http://crowdsec:8080
```

As you can see, you can check the API event if LAPI is down. This is because of the caching system.

### 5) Now restart the container and ban range 1.2.3.4 to 1.2.3.7 for 12h

```bash
docker-compose start crowdsec
docker-compose exec crowdsec /usr/local/bin/cscli decisions add --range 1.2.3.4/30 --duration 12h --type ban -o json
```

### 4) Clear cache and get the new remediation

Clear the cache:

```bash
docker-compose run --rm app php ./examples/clear-cache.php $BOUNCER_API_KEY
```

One more time, get the remediation for the IP "1.2.3.4":

```bash
docker-compose run --rm app php ./examples/live-mode/full-example-live-mode.php $BOUNCER_API_KEY 1.2.3.4 http://crowdsec:8080
```

This is a new miss as you can see in logs.

To better understand the library, we encourage you to try the [complete guide](https://github.com/crowdsecurity/php-cs-bouncer/blob/main/docs/complete-guide.md).
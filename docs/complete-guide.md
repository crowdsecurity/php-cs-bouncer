# The complete guide

Discover the CrowdSec Bouncer Library for PHP.

Here is the complete guide to learning how to use the [Bouncer CrowdSec library developed in PHP](https://github.com/crowdsecurity/php-cs-bouncer).


> Goal: At the end of this guide, you will master:
> - the live mode as well as the stream mode for even more performance
> - the cache layers you can use in this library (File System, Redis, Memcached, and more)
> - the cap remediation level
> - how to get the logged events

We will start from a minimum configuration and improve it step by step. Let's get started and follow the guide!

## 1) Prerequisite

Through this guide, the library you are going to discover will need to communicate with a **CrowdSec** instance, to use different cache layers such as the **file system**, **Redis**, or even **Memcached**. The easiest way is to use Docker, which will greatly simplify our task, whatever platform you like to work with (Linux, BSD, OSX, Windows, etc). So you should have `docker` and `docker-compose` working on your workstation.

Start by creating an empty directory for your project:

```bash
mkdir my-crowdsec-bouncer && $_
```

Then create a `Dockerfile` file at the root of the project:

```Dockerfile
FROM php:7.4-cli-alpine

RUN apk update \
    && apk add --no-cache curl autoconf make g++ libmemcached-dev \
    && docker-php-source extract \
    && pecl install redis memcached \
    && docker-php-ext-enable redis memcached \
    && docker-php-source delete \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /tmp/*

ADD ./ /app
WORKDIR /app
```

This file will configure a PHP container to add *Composer* and other libraries that we will need later.

Also create a `docker-compose.yml` file containing two instances, the **PHP CLI** instance to run to PHP library and a **CrowdSec** instance:

```yml
version: "3"
services:
  app:
    build:
      context: .
      dockerfile: ./Dockerfile
    volumes: [.:/app]
    env_file: [.app.env]

  crowdsec:
    image: crowdsecurity/crowdsec:latest
    environment: [DISABLE_AGENT=true]
```

Create the `.app.env` file and start CrowdSec container:

```bash
touch .app.env
docker-compose up -d crowdsec
```

Create a bouncer with `cscli` and store the generated key to the `.app.env` file :

```bash
echo "BOUNCER_KEY=`docker-compose exec crowdsec /usr/local/bin/cscli bouncers add bouncer-php-library -o raw`" > .app.env

```

Init the `composer.json` and download the `crowdsec/bouncer` library (just wait a short moment for the first docker image build)

```bash
docker-compose run app composer init --no-interaction
docker-compose run app composer require crowdsec/bouncer
docker-compose run app composer install
```

## Now let's verify your first IP!

Now that the setup is done, you'll enjoy making your first IP verification!

For that, create this short `check-ip.php` script:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

// Init cache adapter

$cacheAdapter = new PhpFilesAdapter('', 0, __DIR__.'/.cache');

// Parse argument

$requestedIp = $argv[1];
if (!$requestedIp) {
    die('Usage: php check-ip.php <api_key>');
}

// Init
$bouncer = new Bouncer();
$bouncer->configure([
    'api_key' => getenv('BOUNCER_KEY'),
    'api_url' => 'http://crowdsec:8080'
    ], $cacheAdapter
);

// Ask remediation to LAPI

echo "\nVerify $requestedIp...\n";
$remediation = $bouncer->getRemediationForIp($requestedIp);
echo "\nResult: $remediation\n\n"; // "ban", "captcha" or "bypass"


```

Now run the php script:

```bash
docker-compose run app php check-ip.php 1.2.3.4
```

Congrats! You successfully obtain remediation about the requested IP from LAPI.

As your CrowdSec instance contains no decisions, you received the result "bypass".

Let's now add a new decision in CrowdSec, for example we will ban the 2.3.4.5/30 for 4h:

```bash
docker-compose exec crowdsec /usr/local/bin/cscli decisions add --range 2.3.4.5/30 --duration 4h --type ban
```

Now, if you run the php script against the `2.3.4.5` IP:

```bash
docker-compose run app php check-ip.php 2.3.4.5
```

LAPI will advise you to ban this IP as it's within the 2.3.4.5/30 range.

## The cache system

If you run this script twice, LAPI will not be called, the cache system will relay the information.

Let's try to stop the `crowdsec` container and re-run the script with the "bad" IP:

```bash
docker-compose stop crowdsec
docker-compose run app php check-ip.php 2.3.4.5
```

For this IP, the cache system will never ask LAPI anymore for the duration of the decision.

Note: By default, a "bypass" decision is stored in the cache for 1 min. You can change this duration while instantiating the library.

Don't forget to restart the crowdsec container before continuing :-)

```bash
docker-compose start crowdsec
```

### Play with other cache layers

As you are using docker, let's play with powerful engines for caching, like Redis or Memcached.

Add these lines at the end of the `docker-compose.yml` file:

```yml
...
services:
...
  redis:
    image: redis:6-alpine
  memcached:
    image: memcached:1-alpine
    
```

And start these two new containers:

```bash
docker-compose up -d redis memcached
```

Now update the PHP script to replace the `PhpFilesAdapter` with the `RedisAdapter`.

Replace:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Symfony\Component\Cache\Adapter\RedisAdapter;

// Init cache adapter
$cacheAdapter = new PhpFilesAdapter('', 0, __DIR__.'/.cache');

...
```

with:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Symfony\Component\Cache\Adapter\RedisAdapter;

// Init cache adapter

$cacheAdapter = new RedisAdapter(RedisAdapter::createConnection('redis://redis:6379'));

...
```

Or, if `Memcached` is more adapted than `Redis` to your needs:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

// Init cache adapter

$cacheAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection('memcached://memcached:11211'));

...
```

You will still be able to verify IPs, but the cache system will be extremely efficient!

```bash
docker-compose run app php check-ip.php 2.3.4.5
```

Congrats! Now you use a very efficient cache layer!

> Note: You can try more cache systems but we did not test them for now (Apcu, Filesystem, Doctrine, Couchbase, Pdo). The [full list is here](https://symfony.com/doc/current/components/cache.html#available-cache-adapters).

## Cap remediation level

In some cases, it's a critical action to ban access to users (ex: e-commerce). We prefer to let user access to the website, even if CrowdSec says "ban it!".

Fortunately, this library allows you to cap the remediation to a certain level.

Let's add the `max_remediation_level` configuration with `captcha` value:

```php
$bouncer->configure([
    'api_key' => getenv('BOUNCER_KEY'),
    'api_url' => 'http://crowdsec:8080',
    'max_remediation_level' => 'captcha' // <== ADD THIS LINE!
    ], $cacheAdapter
);
```

Now if you call one more time:

```bash
docker-compose run app php check-ip.php 2.3.4.5
```

The library will cap the value to `captcha` level.

Note: the list of levels is [here](https://github.com/crowdsecurity/php-cs-bouncer/blob/572381192913e9abd338cd3c9917adc3853c2244/src/Constants.php#L40).


## Clear the cache

To clear the cache, it's easy as:

```php
$bouncer->clearCache();
```

## Log relevant events

This library uses the popular monolog library to log events.

For example, to display all event (including DEBUG) events and also store events (>INFO) to a file:

Near the top section of the script, add:

```php
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
```

and replace these lines:

```php
// Instanciate the bouncer
$bouncer = new Bouncer();
```

with:

```php
// Instanciate the Stream logger with info level(optional)
$logger = new Logger('example');

// Display logs with INFO verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Store logs with WARNING verbosity
$fileHandler = new RotatingFileHandler(__DIR__.'/crowdsec.log', 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Instanciate the bouncer
$bouncer = new Bouncer($logger);
```

## Important note about cache expiration

As said in the [Symfony Cache Component Documentation](https://symfony.com/doc/current/components/cache/cache_pools.html#pruning-cache-items):

> Some cache pools do not include an automated mechanism for pruning expired cache items. For example, the [FilesystemAdapter](https://symfony.com/doc/current/components/cache/adapters/filesystem_adapter.html#component-cache-filesystem-adapter) cache does not remove expired cache items until an item is explicitly requested and determined to be expired, for example, via a call to Psr\Cache\CacheItemPoolInterface::getItem. Under certain workloads, this can cause stale cache entries to persist well past their expiration, resulting in a sizable consumption of wasted disk or memory space from excess, expired cache items.

This shortcoming has been solved through the introduction of the prune method:

```php
$bouncer->prune();
```

## Using the stream mode

It's incredibly fast. But sometimes, we really need to be faster.

For example, during the first check of an IP, you will notice that it is still necessary to go and request the info from LAPI.

How to remove this first small delay? LAPI implements a "Stream mode" and we will use it.

For this, we will write another script called `refresh-cache.php`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


// Init cache adapter

$cacheAdapter = new MemcachedAdapter(MemcachedAdapter::createConnection('memcached://memcached:11211'));


// Instanciate the Stream logger with info level(optional)
$logger = new Logger('example');
$fileHandler = new RotatingFileHandler(__DIR__.'/crowdsec.log', 0, Logger::WARNING);
$logger->pushHandler($fileHandler);

// Instanciate the bouncer
$bouncer = new Bouncer($logger);
$bouncer->configure([
    'api_key' => getenv('BOUNCER_KEY'),
    'api_url' => 'http://crowdsec:8080'
    ], $cacheAdapter
);

// Refresh the blocklist cache
$bouncer->refreshBlocklistCache();
echo "Cache successfully refreshed.\n";

```

> **Important note!**
>
> Check the line `$cacheAdapter = new ...`.
>
> Don't forget to use the same cache system as the one you used in the **check-ip.php** script.

```bash
docker-compose run app php refresh-cache.php
```

Now you can request as much remediation as you need without never overloading LAPI! Pretty nice!

Let's try to stop the `crowdsec` container and re-run the script with the "bad" IP:

```bash
docker-compose stop crowdsec
docker-compose run app php check-ip.php 2.3.4.5
```

> Even if CrowdSec LAPI is down, your bouncer can get the correct information.

To stay protected, you have to call the **refresh-cache.php** script periodically (ie each 30seconds). To do so you can use `crontab` like systems but we will not see that in this guide.

## Enjoy implementing nice PHP bouncers!

This is the end of this guide about how to use the CrowdSec Bouncer PHP library.

If you have some comments, don't hesitate!
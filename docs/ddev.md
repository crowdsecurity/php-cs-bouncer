# DDEV stack

There are many ways to install this library on a local PHP environment.

We are using [DDEV-Local](https://ddev.readthedocs.io/en/stable/) because it is quite simple to use and customize.

You may use your own local stack, but we provide here some useful tools that depends on DDEV.


<!-- START doctoc generated TOC please keep comment here to allow auto update -->

<!-- END doctoc generated TOC please keep comment here to allow auto update -->


## DDEV-Local setup

For a quick start, follow the below steps.


### DDEV installation

Please follow the [official instructions](https://ddev.readthedocs.io/en/stable/#installation). On a Linux
distribution, this should be as simple as

    sudo apt-get install linuxbrew-wrapper
    brew tap drud/ddev && brew install ddev


### Prepare DDEV PHP environment

The final structure of the project will look like below.

```
php-project-sources
│   
│ (your php project sources; could be a simple index.html file)    
│
└───.ddev
│   │   
│   │ (Cloned sources of a PHP specific ddev repo)
│   
└───my-own-modules
    │   
    │
    └───crowdsec-php-lib
       │   
       │ (Clone of this repo)
         
```

- Create an empty folder that will contain all necessary sources:
```
mkdir php-project-sources
```

- Create a `crowdsec-php-lib` folder with sources of this repo:

```
cd php-project-sources
mkdir my-own-modules && mkdir my-own-modules/crowdsec-php-lib
cd my-own-modules/crowdsec-php-lib && git clone git@github.com:crowdsecurity/php-cs-bouncer.git ./
```

- Create an empty `.ddev` folder for DDEV and clone our pre-configured DDEV repo:

```
cd php-project-sources
mkdir .ddev && cd .ddev && git clone git@github.com:julienloizelet/ddev-php.
git ./
```
- Copy some configurations file:

By default, ddev will launch a PHP 7.2 container. If you want to work with another PHP version, copy the 
corresponding config file. For example:

```
cd php-project-sources
cp .ddev/config_overrides/config.php74.yaml .ddev/config.php74.yaml
```
- Launch DDEV

```
cd .ddev && ddev start
```
This should take some times on the first launch as this will download all necessary docker images.

 
## Usage


### Add CrowdSec bouncer and watcher

- To create a new bouncer in the crowdsec container, run:

```
ddev create-bouncer [name]
```

It will return the bouncer key.

- To create a new watcher, run:

```
ddev create-watcher [name] [password]
```


### Use composer to update or install the lib

Run:

```
ddev composer update --working-dir ./my-own-modules/crowdsec-php-lib
```

### Unit test

First, create a bouncer and keep the result key. 

```
ddev create-bouncer
```

Then, create a specific watcher for unit test:

```
ddev create-watcher PhpUnitTestMachine PhpUnitTestMachinePassword
```

Finally, run 


```
ddev exec BOUNCER_KEY=your-bouncer-key LAPI_URL=http://crowdsec:8080 MEMCACHED_DSN=memcached://memcached:11211 REDIS_DSN=redis://redis:6379 /usr/bin/php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit --testdox --colors --exclude-group ignore ./my-own-modules/crowdsec-php-lib/tests/IpVerificationTest.php
```

### Use a `check-ip` php script for test


Create this short `check-ip.php` script in your root folder:

```php
<?php

require __DIR__ . '/my-own-modules/crowdsec-php-lib/vendor/autoload.php';

use CrowdSecBouncer\Bouncer;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Init cache adapter

$cacheAdapter = new PhpFilesAdapter('', 0, __DIR__.'/.cache');

// Parse argument

$requestedIp = $argv[1];
$bouncerKey = $argv[2];
if (!$requestedIp || !$bouncerKey) {
    die('Usage: php check-ip.php <IP> <BOUNCER_KEY>');
}
// Instantiate the Stream logger with info level(optional)
$logger = new Logger('example');

// Display logs with INFO verbosity
$streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$streamHandler->setFormatter(new LineFormatter("[%datetime%] %message% %context%\n"));
$logger->pushHandler($streamHandler);

// Store logs with WARNING verbosity
$fileHandler = new RotatingFileHandler(__DIR__.'/crowdsec.log', 0, Logger::DEBUG);
$logger->pushHandler($fileHandler);

// Init
$bouncer = new Bouncer($cacheAdapter, $logger);
$bouncer->configure([
    'api_key' => $bouncerKey,
    'api_url' => 'http://crowdsec:8080'
]
);

// Ask remediation to LAPI

echo "\nVerify $requestedIp...\n";
$remediation = $bouncer->getRemediationForIp($requestedIp);
echo "\nResult: $remediation\n\n"; // "ban", "captcha" or "bypass"
```

To run this script, you have to know your bouncer key `<BOUNCER_KEY>` and run
```command
ddev exec php check-ip.php <IP> <BOUNCER_KEY>
```

As a reminder, your bouncer key is returned by the `ddev create-bouncer` command.

For example, run the php script:

```bash
ddev exec php check-ip.php 1.2.3.4 <BOUNCER_KEY>
```

As your CrowdSec instance contains no decisions, you received the result "bypass".

Let's now add a new decision in CrowdSec, for example we will ban the 1.2.3.4/30 for 4h:

```bash
ddev exec -s crowdsec cscli decisions add --range 1.2.3.4/30 --duration 4h --type ban
```

Now, if you run the php script against the `1.2.3.4` IP:

```bash
ddev exec php check-ip.php 1.2.3.4 <BOUNCER_KEY>
```

LAPI will advise you to ban this IP as it's within the 1.2.3.4/30 range.


### Coding standards

#### PHPCS

We are using the [PHP Coding Standards Fixer](https://cs.symfony.com/)

With ddev, you can do the following:

```command
ddev composer update --working-dir=./my-own-modules/crowdsec-php-lib/tools/php-cs-fixer
```
And then:

```
ddev exec PHP_CS_FIXER_IGNORE_ENV=1 ./my-own-modules/crowdsec-php-lib/tools/php-cs-fixer/vendor/bin/php-cs-fixer fix ./my-own-modules/crowdsec-php-lib

```

#### PHP Mess Detector

To use the `phpmd` tool, you can run:

```
ddev phpmd ./my-own-modules/crowdsec-php-lib tools/phpmd/rulesets.xml src

```
![CrowdSec Logo](images/logo_crowdsec.png)
# CrowdSec Bouncer PHP library

## Developer guide


<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Local development](#local-development)
  - [DDEV-Local setup](#ddev-local-setup)
    - [DDEV installation](#ddev-installation)
    - [Prepare DDEV PHP environment](#prepare-ddev-php-environment)
  - [DDEV Usage](#ddev-usage)
    - [Add CrowdSec bouncer and watcher](#add-crowdsec-bouncer-and-watcher)
    - [Use composer to update or install the lib](#use-composer-to-update-or-install-the-lib)
    - [Find IP of your docker services](#find-ip-of-your-docker-services)
    - [Unit test](#unit-test)
    - [Auto-prepend mode (standalone mode)](#auto-prepend-mode-standalone-mode)
    - [End-to-end tests](#end-to-end-tests)
    - [Coding standards](#coding-standards)
      - [PHPCS Fixer](#phpcs-fixer)
      - [PHP Mess Detector](#php-mess-detector)
      - [PHPCS and PHPCBF](#phpcs-and-phpcbf)
    - [Generate CrowdSec tools and settings on start](#generate-crowdsec-tools-and-settings-on-start)
- [Quick start guide](#quick-start-guide)
  - [Check IP script](#check-ip-script)
    - [Cap remediation level](#cap-remediation-level)
    - [Play with other cache layers](#play-with-other-cache-layers)
  - [Clear cache script](#clear-cache-script)
  - [Full Live mode example](#full-live-mode-example)
    - [Set up the context](#set-up-the-context)
    - [Get the remediation the clean IP "1.2.3.4"](#get-the-remediation-the-clean-ip-1234)
    - [Simulate LAPI down by using a bad url](#simulate-lapi-down-by-using-a-bad-url)
    - [Now ban range 1.2.3.4 to 1.2.3.7 for 12h](#now-ban-range-1234-to-1237-for-12h)
    - [Clear cache and get the new remediation](#clear-cache-and-get-the-new-remediation)
- [Discover the CrowdSec LAPI](#discover-the-crowdsec-lapi)
  - [Use the CrowdSec cli (`cscli`)](#use-the-crowdsec-cli-cscli)
    - [Add decision for an IP or a range of IPs](#add-decision-for-an-ip-or-a-range-of-ips)
    - [Add decision to ban or captcha a country](#add-decision-to-ban-or-captcha-a-country)
    - [Delete decisions](#delete-decisions)
    - [Create a bouncer](#create-a-bouncer)
    - [Create a watcher](#create-a-watcher)
  - [Use the web container to call LAPI](#use-the-web-container-to-call-lapi)
- [Commit message](#commit-message)
  - [Allowed message `type` values](#allowed-message-type-values)
- [Release process](#release-process)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->



## Local development

There are many ways to install this library on a local PHP environment.

We are using [DDEV-Local](https://ddev.readthedocs.io/en/stable/) because it is quite simple to use and customize.

Of course, you may use your own local stack, but we provide here some useful tools that depends on DDEV.


### DDEV-Local setup

For a quick start, follow the below steps.


#### DDEV installation

This project is fully compatible with DDEV 1.18.2 and it is recommended to use this specific version.
For the DDEV installation, please follow the [official instructions](https://ddev.readthedocs.io/en/stable/#installation).
On a Linux distribution, you can run:
```
sudo apt-get -qq update
sudo apt-get -qq -y install libnss3-tools
curl -LO https://raw.githubusercontent.com/drud/ddev/master/scripts/install_ddev.sh
bash install_ddev.sh v1.18.2
rm install_ddev.sh
```


#### Prepare DDEV PHP environment

The final structure of the project will look like below.

```
php-project-sources
│   
│ (your php project sources; could be a simple index.php file)    
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
```bash
mkdir php-project-sources
```

- Create a `crowdsec-php-lib` folder with sources of this repo:

```bash
cd php-project-sources
mkdir my-own-modules && mkdir my-own-modules/crowdsec-php-lib
cd my-own-modules/crowdsec-php-lib && git clone git@github.com:crowdsecurity/php-cs-bouncer.git ./
```

- Create an empty `.ddev` folder for DDEV and clone our pre-configured DDEV repo:

```bash
cd php-project-sources
mkdir .ddev && cd .ddev && git clone git@github.com:julienloizelet/ddev-php.git ./
```
- Copy some configurations file:

```bash
cd php-project-sources
cp .ddev/additional_docker_compose/docker-compose.crowdsec.yaml .ddev/docker-compose.crowdsec.yaml
cp .ddev/additional_docker_compose/docker-compose.playwright.yaml .ddev/docker-compose.playwright.yaml
```

By default, ddev will launch a PHP 7.2 container. If you want to work with another PHP version, copy the
corresponding config file. For example:

```bash
cd php-project-sources
cp .ddev/config_overrides/config.php74.yaml .ddev/config.php74.yaml
```
- Launch DDEV

```bash
cd .ddev && ddev start
```
This should take some times on the first launch as this will download all necessary docker images.


### DDEV Usage


#### Add CrowdSec bouncer and watcher

- To create a new bouncer in the crowdsec container, run:

```bash
ddev create-bouncer [name]
```

It will return the bouncer key.

- To create a new watcher, run:

```bash
ddev create-watcher [name] [password]
```


#### Use composer to update or install the lib

Run:

```bash
ddev composer update --working-dir ./my-own-modules/crowdsec-php-lib
```

#### Find IP of your docker services

In most cases, you will test to bounce your current IP. As we are running on a docker stack, this is the local host IP.

To find it, just run: 

```
ddev find-ip
```

You will have to know also the IP of the `ddev-router` container as it acts as a proxy, and you should set it in the
`trust_ip_forward_array` setting.

To find this IP, just run:

```
ddev find-ip ddev-router
```



#### Unit test

First, create a bouncer and keep the result key.

```bash
ddev create-bouncer
```

Then, create a specific watcher for unit test:

```bash
ddev create-watcher PhpUnitTestMachine PhpUnitTestMachinePassword
```

Finally, run


```bash
ddev exec BOUNCER_KEY=your-bouncer-key LAPI_URL=http://crowdsec:8080 MEMCACHED_DSN=memcached://memcached:11211 REDIS_DSN=redis://redis:6379 /usr/bin/php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit --testdox --colors --exclude-group ignore ./my-own-modules/crowdsec-php-lib/tests/IpVerificationTest.php
```

For geolocation Unit Test, you should first put 2 free MaxMind databases in the `tests` folder : `GeoLite2-City.mmdb`
and`GeoLite2-Country.mmdb`. You can download these databases by creating a maxmind account and browse to [the download page](https://www.maxmind.com/en/accounts/current/geoip/downloads).


Then, you can run:

```bash
ddev exec BOUNCER_KEY=your-bouncer-key LAPI_URL=http://crowdsec:8080  /usr/bin/php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit --testdox --colors --exclude-group ignore ./my-own-modules/crowdsec-php-lib/tests/GeolocationTest.php

```


#### Auto-prepend mode (standalone mode)

Before using the bouncer in a standalone mode (i.e. with an auto-prepend directive), you should copy the
[`scripts/auto-prepend/settings.example.php`](../scripts/auto-prepend/settings.example.php) file to a `scripts/auto-prepend/settings.
php` and edit it depending on your needs.


Then, to configure the Nginx service in order that it uses an auto-prepend directive pointing to the
[`scripts/auto-prepend/bounce.php`](../scripts/auto-prepend/bounce.php) script, please run the
following command from the `.ddev` folder:

```bash
ddev crowdsec-prepend-nginx
```

With that done, every access to your ddev url (i.e. `https://phpXX.ddev.site` where `XX` is your php version) will
be bounce.

For example, you should try to browse the following url:

```
https://phpXX.ddev.site/my-own-modules/crowdsec-php-lib/scripts/public/protected-page.php
```

#### End-to-end tests

In auto-prepend mode, you can run some end-to-end tests.

Before running the tests, you have to copy some testing scripts:

```
cd php-project-sources
cp .ddev/custom_files/crowdsec/cache-actions.php my-own-modules/crowdsec-php-lib/scripts/public/cache-actions.php
cp .ddev/custom_files/crowdsec/geolocation-test.php my-own-modules/crowdsec-php-lib/scripts/public/geolocation-test.php
```

We are using a Jest/Playwright Node.js stack to launch a suite of end-to-end tests.

Tests code is in the `tests/end-to-end` folder. You should have to `chmod +x` the scripts you will find in  
`tests/end-to-end/__scripts__`.


Then you can use the `run-test.sh` script to run the tests:

- the first parameter specifies if you want to run the test on your machine (`host`) or in the
  docker containers (`docker`). You can also use `ci` if you want to have the same behavior as in Github action.
- the second parameter list the test files you want to execute. If empty, all the test suite will be launched.

For example:

    ./run-tests.sh host "./__tests__/1-live-mode.js"
    ./run-tests.sh docker "./__tests__/1-live-mode.js" 
    ./run-tests.sh host

Before testing with the `docker` or `ci` parameter, you have to install all the required dependencies
in the playwright container with this command :

    ./test-init.sh

If you want to test with the `host` parameter, you will have to install manually all the required dependencies:

```bash
yarn --cwd ./tests/end-to-end --force
yarn global add cross-env
```

#### Coding standards

##### PHPCS Fixer

We are using the [PHP Coding Standards Fixer](https://cs.symfony.com/)

With ddev, you can do the following:

```bash
ddev composer update --working-dir=./my-own-modules/crowdsec-php-lib/tools/php-cs-fixer
```
And then:

```bash
ddev phpcsfixer my-own-modules/crowdsec-php-lib tools/php-cs-fixer

```

**N.B**: to use PHPCS Fixer, you will need to work with a PHP version >= 7.4.

##### PHP Mess Detector

To use the [PHPMD](https://github.com/phpmd/phpmd) tool, you can run:

```bash
ddev phpmd ./my-own-modules/crowdsec-php-lib tools/phpmd/rulesets.xml src

```

##### PHPCS and PHPCBF

To use [PHP Code Sniffer](https://github.com/squizlabs/PHP_CodeSniffer) tools, you can run:

```bash
ddev phpcs ./my-own-modules/crowdsec-php-lib/vendor/bin/phpcs my-own-modules/crowdsec-php-lib/src
```

and:

```bash
ddev phpcbf ./my-own-modules/crowdsec-php-lib/vendor/bin/phpcs my-own-modules/crowdsec-php-lib/src
```


#### Generate CrowdSec tools and settings on start

We use a post-start DDEV hook to:
- Create a bouncer
- Set bouncer key, api url and other needed values in the `scripts/auto-prepend/settings.php` file (useful to test
  standalone mode).
- Create a watcher that we use in end-to-end tests

Just copy the file and restart:
```bash
cp .ddev/config_overrides/config.crowdsec.yaml .ddev/config.crowdsec.yaml
ddev restart
```   


## Quick start guide

> Goal: At the end of this guide, you will understand better:
> - the live mode as well as the stream mode for even more performance
> - the cache layers you can use in this library (File System, Redis, Memcached, and more)
> - the cap remediation level
> - how to get the logged events

You will find some php scripts in the `scripts` folder.

**N.B** : If you are not using DDEV, you can replace all `ddev exec php ` by `php` and specify the right script paths.

### Check IP script

The [`check-ip`](../scripts/check-ip.php) script will get the remediation (`bypass`, `captcha` or `ban`) for some IP.

To run this script, you have to know your bouncer key `<BOUNCER_KEY>` and run
```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/check-ip.php <IP> <BOUNCER_KEY>
```

As a reminder, your bouncer key is returned by the `ddev create-bouncer` command.

For example, run the php script:

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/check-ip.php 1.2.3.4 <BOUNCER_KEY>
```

As your CrowdSec instance contains no decisions, you received the result "bypass".

Let's now add a new decision in CrowdSec, for example we will ban the 1.2.3.4/30 for 4h:

```bash
ddev exec -s crowdsec cscli decisions add --range 1.2.3.4/30 --duration 4h --type ban
```

Now, if you run the php script against the `1.2.3.4` IP:

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/check-ip.php 1.2.3.4 <BOUNCER_KEY>
```

LAPI will advise you to ban this IP as it's within the 1.2.3.4/30 range.

#### Cap remediation level

In some cases, it's a critical action to ban access to users (ex: e-commerce). We prefer to let user access to the website, even if CrowdSec says "ban it!".

Fortunately, this library allows you to cap the remediation to a certain level.

Let's add the `max_remediation_level` configuration with `captcha` value:

```php
$bouncer->configure([
    'api_key' => $bouncerKey,
    'api_url' => 'http://crowdsec:8080',
    'max_remediation_level' => 'captcha' // <== ADD THIS LINE!
    ]
);
```

Now if you call one more time:

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/check-ip.php 1.2.3.4 <BOUNCER_KEY>
```

The library will cap the value to `captcha` level.


#### Play with other cache layers

Now update the `check-ip.php`script to replace the `PhpFilesAdapter` with the `RedisAdapter`.

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

You will still be able to verify IPs, but the cache system will be more efficient.

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/check-ip.php 1.2.3.4 <BOUNCER_KEY>
```

> Note: You can try more cache systems but we did not test them for now (Apcu, Filesystem, Doctrine, Couchbase, Pdo). The [full list is here](https://symfony.com/doc/current/components/cache.html#available-cache-adapters).

### Clear cache script

To clear your LAPI cache, you can use the [`clear-php`](../scripts/clear-cache.php) script: 

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/clear-cache.php <BOUNCER_KEY>
```

### Full Live mode example

This example demonstrates how the PHP Lib works with cache when you are using the live mode.

We will use here the [`full-example-live-mode.php`](../scripts/full-example-live-mode.php).

#### Set up the context

Start the containers:

```bash
ddev start
```

Then get a bouncer API key by copying the result of:

```bash
ddev create-bouncer`
```

#### Get the remediation the clean IP "1.2.3.4"

Try with the `full-example-live-mode.php` file:


```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/full-example-live-mode.php <YOUR_BOUNCER_KEY> 1.2.3.4 http://crowdsec:8080
```

#### Simulate LAPI down by using a bad url

If you run this script twice, LAPI will not be called, the cache system will relay the information.
You can this behaviour by testing with a bad LAPI url.

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/full-example-live-mode.php <YOUR_BOUNCER_KEY> 1.2.3.4 http://crowdsec:BAD
```

As you can see, you can check the API event if LAPI is down. This is because of the caching system.

#### Now ban range 1.2.3.4 to 1.2.3.7 for 12h

```bash
ddev exec -s crowdsec cscli decisions add --range 1.2.3.4/30 --duration 12h --type ban
```

#### Clear cache and get the new remediation

Clear the cache:

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/clear-cache.php <YOUR_BOUNCER_KEY>
```

One more time, get the remediation for the IP "1.2.3.4":

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/full-example-live-mode.php <YOUR_BOUNCER_KEY> 1.2.3.4 http://crowdsec:8080
```

This is a ban (and cache miss) as you can see in your terminal logs.


## Discover the CrowdSec LAPI

This library interacts with a CrowdSec agent that you have installed on an accessible server.

The easiest way to interact with the local API (LAPI) is to use the `cscli` tool,but it is also possible to contact it
through a certain URL (e.g. `http://crowdsec:8080`).

### Use the CrowdSec cli (`cscli`)


Please refer to the [CrowdSec cscli documentation](https://docs.crowdsec.net/docs/cscli/cscli/) for an exhaustive
list of commands.

**N.B**: If you are using DDEV, just replace `cscli` with `ddev exec -s crowdsec cscli`.

Here is a list of command that we often use to test the PHP library:

#### Add decision for an IP or a range of IPs

First example is a `ban`, second one is a `captcha`:

```bash
cscli decisions add --ip <SOME_IP> --duration 12h --type ban
cscli decisions add --ip <SOME_IP> --duration 4h --type captcha
```

For a range of IPs:

```bash
cscli decisions add --range 1.2.3.4/30 --duration 12h --type ban
```

#### Add decision to ban or captcha a country
```bash
cscli decisions add --scope Country --value JP --duration 4h --type ban
```

#### Delete decisions

- Delete all decisions:
```bash
cscli decisions delete --all 
```
- Delete a decision with an IP scope
```bash
cscli decisions delete -i <SOME_IP>>
```

#### Create a bouncer


```bash
cscli bouncers add <BOUNCER_NAME> -o raw
```

With DDEV, an alias is available:

```bash
ddev create-bouncer <BOUNCER_NAME>
```

#### Create a watcher


```bash
cscli machines add <SOME_LOGIN> --password <SOME_PASSWORD> -o raw
```

With DDEV, an alias is available:

```bash
ddev create-watcher <SOME_LOGIN> <SOME_PASSWORD>
```


### Use the web container to call LAPI

Please see the [CrowdSec LAPI documentation](https://crowdsecurity.github.io/api_doc/index.html?urls.primaryName=LAPI) 
for an 
exhaustive list of available calls.

If you are using DDEV, you can enter the web by running:

```bash
ddev exec bash
````

Then, you should use some `curl` calls to contact the LAPI.

For example, you can get the list of decisions with commands like:

```bash
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" http://crowdsec:8080/v1/decisions | jq
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" http://crowdsec:8080/v1/decisions?ip=1.2.3.4 | jq
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" http://crowdsec:8080/v1/decisions/stream?startup=true | jq
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" http://crowdsec:8080/v1/decisions/stream | jq
```

## Commit message

In order to have an explicit commit history, we are using some commits message convention with the following format:

    <type>(<scope>): <subject>

Allowed `type` are defined below.
`scope` value intends to clarify which part of the code has been modified. It can be empty or `*` if the change is a
global or difficult to assign to a specific part.
`subject` describes what has been done using the imperative, present tense.

Example:

    feat(admin): Add css for admin actions


You can use the `commit-msg` git hook that you will find in the `.githooks` folder : 

```
cp .githooks/commit-msg .git/hooks/commit-msg
chmod +x .git/hooks/commit-msg
```

### Allowed message `type` values

- chore (automatic tasks; no production code change)
- ci (updating continuous integration process; no production code change)
- comment (commenting;no production code change)
- docs (changes to the documentation)
- feat (new feature for the user)
- fix (bug fix for the user)
- refactor (refactoring production code)
- style (formatting; no production code change)
- test (adding missing tests, refactoring tests; no production code change)

## Release process

We are using [semantic versioning](https://semver.org/) to determine a version number. To verify the current tag, 
you should run: 
```
git describe --tags `git rev-list --tags --max-count=1`
```

Before publishing a new release, there are some manual steps to take:

- Change the version number in the `Constants.php` file
- Update the `CHANGELOG.md` file

Then, you have to [run the action manually from the GitHub repository](https://github.com/crowdsecurity/php-cs-bouncer/actions/workflows/release.yml)


Alternatively, you could use the [Github CLI](https://github.com/cli/cli): 
- create a draft release: 
```
gh workflow run release.yml -f tag_name=vx.y.z -f draft=true
```
- publish a prerelease:  
```
gh workflow run release.yml -f tag_name=vx.y.z -f prerelease=true
```
- publish a release: 
```
gh workflow run release.yml -f tag_name=vx.y.z
```

Note that the Github action will fail if the tag `tag_name` already exits.


 

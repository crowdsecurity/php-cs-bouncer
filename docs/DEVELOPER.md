![CrowdSec Logo](images/logo_crowdsec.png)
# CrowdSec Bouncer PHP library

## Developer guide


<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Local development](#local-development)
  - [DDEV setup](#ddev-setup)
    - [DDEV installation](#ddev-installation)
    - [Prepare DDEV PHP environment](#prepare-ddev-php-environment)
  - [DDEV Usage](#ddev-usage)
    - [Add CrowdSec bouncer and watcher](#add-crowdsec-bouncer-and-watcher)
    - [Use composer to update or install the lib](#use-composer-to-update-or-install-the-lib)
    - [Find IP of your docker services](#find-ip-of-your-docker-services)
    - [Unit test](#unit-test)
    - [Integration test](#integration-test)
    - [Auto-prepend mode (standalone mode)](#auto-prepend-mode-standalone-mode)
    - [End-to-end tests](#end-to-end-tests)
    - [Coding standards](#coding-standards)
      - [PHPCS Fixer](#phpcs-fixer)
      - [PHPSTAN](#phpstan)
      - [PHP Mess Detector](#php-mess-detector)
      - [PHPCS and PHPCBF](#phpcs-and-phpcbf)
      - [PSALM](#psalm)
      - [PHP Unit Code coverage](#php-unit-code-coverage)
    - [Generate CrowdSec tools and settings on start](#generate-crowdsec-tools-and-settings-on-start)
    - [Redis debug](#redis-debug)
    - [Memcached debug](#memcached-debug)
- [Example scripts](#example-scripts)
  - [Clear cache script](#clear-cache-script)
  - [Full Live mode example](#full-live-mode-example)
    - [Set up the context](#set-up-the-context)
    - [Get the remediation the clean IP "1.2.3.4"](#get-the-remediation-the-clean-ip-1234)
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

We are using [DDEV](https://ddev.readthedocs.io/en/stable/) because it is quite simple to use and customize.

Of course, you may use your own local stack, but we provide here some useful tools that depends on DDEV.


### DDEV setup

For a quick start, follow the below steps.


#### DDEV installation

This project is fully compatible with DDEV 1.21.4, and it is recommended to use this specific version.
For the DDEV installation, please follow the [official instructions](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/).


#### Prepare DDEV PHP environment

The final structure of the project will look like below.

```
php-project-sources (choose the name you want for this folder)
│   
│ (your php project sources; could be a simple index.php file)    
│
└───.ddev (do not change this folder name)
│   │   
│   │ (Cloned sources of a PHP specific ddev repo)
│   
└───my-own-modules (do not change this folder name)
    │
    │
    └───crowdsec-php-lib (do not change this folder name)
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

By default, ddev will launch a PHP 7.2 container. If you want to work with another PHP version, copy the corresponding configuration  file. For example:

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

- To create a new bouncer in the CrowdSec container, run:

```bash
ddev create-bouncer [name]
```

It will return the bouncer key.

- To create a new watcher, run:

```bash
ddev create-watcher [name] [password]
```

**N.B.** : Since we are using TLS authentication for agent, you should avoid to create a watcher with this method.


#### Use composer to update or install the lib

Run:

```bash
ddev composer update --working-dir ./my-own-modules/crowdsec-php-lib
```

#### Find IP of your docker services

In most cases, you will test to bounce your current IP. As we are running on a docker stack, this is the local host IP.

To find it, just run: 

```bash
ddev find-ip
```

You will have to know also the IP of the `ddev-router` container as it acts as a proxy, and you should set it in the `trust_ip_forward_array` setting.

To find this IP, just run:

```bash
ddev find-ip ddev-router
```


#### Unit test


```bash
ddev php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit  ./my-own-modules/crowdsec-php-lib/tests/Unit --testdox
```

#### Integration test

First, create a bouncer and keep the result key.

```bash
ddev create-bouncer
```

Then, as we use a TLS ready CrowdSec container, you have to copy some certificates and key:

```bash
cd php-project-sources
mkdir cfssl
cp -r ../.ddev/custom_files/crowdsec/cfssl/* cfssl
```

Finally, run


```bash
ddev exec BOUNCER_KEY=your-bouncer-key AGENT_TLS_PATH=/var/www/html/cfssl LAPI_URL=https://crowdsec:8080 
MEMCACHED_DSN=memcached://memcached:11211 REDIS_DSN=redis://redis:6379 /usr/bin/php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit --testdox --colors --exclude-group ignore ./my-own-modules/crowdsec-php-lib/tests/Integration/IpVerificationTest.php
```

For geolocation Unit Test, you should first put 2 free MaxMind databases in the `tests` folder : `GeoLite2-City.mmdb`
and `GeoLite2-Country.mmdb`. You can download these databases by creating a MaxMind account and browse to [the download page](https://www.maxmind.com/en/accounts/current/geoip/downloads).


Then, you can run:

```bash
ddev exec BOUNCER_KEY=your-bouncer-key AGENT_TLS_PATH=/var/www/html/cfssl LAPI_URL=https://crowdsec:8080  /usr/bin/php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit --testdox --colors --exclude-group ignore ./my-own-modules/crowdsec-php-lib/tests/Integration/GeolocationTest.php
```

**N.B.**: If you want to test with `curl` instead of `file_get_contents` calls to LAPI, you have to add `USE_CURL=1` in 
the previous commands.

**N.B**.: If you want to test with `tls` authentification, you have to add `BOUNCER_TLS_PATH` environment variable 
and specify the path where you store certificates and keys. For example:

```bash
ddev exec USE_CURL=1 AGENT_TLS_PATH=/var/www/html/cfssl  BOUNCER_TLS_PATH=/var/www/html/cfssl LAPI_URL=https://crowdsec:8080 MEMCACHED_DSN=memcached://memcached:11211 REDIS_DSN=redis://redis:6379 /usr/bin/php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit --testdox --colors --exclude-group ignore ./my-own-modules/crowdsec-php-lib/tests/Integration/IpVerificationTest.php
```


#### Auto-prepend mode (standalone mode)

Before using the bouncer in a standalone mode (i.e. with an auto-prepend directive), you should copy the [`scripts/auto-prepend/settings.example.php`](../scripts/auto-prepend/settings.example.php) file to a `scripts/auto-prepend/settings.php` and edit it depending on your needs.

Then, to configure the Nginx service in order that it uses an auto-prepend directive pointing to the [`scripts/auto-prepend/bounce.php`](../scripts/auto-prepend/bounce.php) script, please run the following command from the `.ddev` folder:

```bash
ddev crowdsec-prepend-nginx
```

With that done, every access to your ddev url (i.e. `https://phpXX.ddev.site` where `XX` is your php version) will be bounce.

For example, you should try to browse the following url:

```
https://phpXX.ddev.site/my-own-modules/crowdsec-php-lib/scripts/public/protected-page.php
```

#### End-to-end tests

In auto-prepend mode, you can run some end-to-end tests.

We are using a Jest/Playwright Node.js stack to launch a suite of end-to-end tests.

Tests code is in the `tests/end-to-end` folder. You should have to `chmod +x` the scripts you will find in `tests/end-to-end/__scripts__`.


Then you can use the `run-test.sh` script to run the tests:

- the first parameter specifies if you want to run the test on your machine (`host`) or in the
  docker containers (`docker`). You can also use `ci` if you want to have the same behavior as in GitHub action.
- the second parameter list the test files you want to execute. If empty, all the test suite will be launched.

For example:

    ./run-tests.sh host "./__tests__/1-live-mode.js"
    ./run-tests.sh docker "./__tests__/1-live-mode.js" 
    ./run-tests.sh host

Before testing with the `docker` or `ci` parameter, you have to install all the required dependencies in the playwright container with this command :

    ./test-init.sh

If you want to test with the `host` parameter, you will have to install manually all the required dependencies:

```bash
yarn --cwd ./tests/end-to-end --force
yarn global add cross-env
```

#### Coding standards

We set up some coding standards tools that you will find in the `tools/coding-standards` folder. In order to use these, you will need to work with a PHP version >= 7.4 and run first:

```
ddev composer update --working-dir=./my-own-modules/crowdsec-php-lib/tools/coding-standards
```

##### PHPCS Fixer

We are using the [PHP Coding Standards Fixer](https://cs.symfony.com/). With ddev, you can do the following:


```bash
ddev phpcsfixer my-own-modules/crowdsec-php-lib/tools/coding-standards/php-cs-fixer ../

```

##### PHPSTAN

To use the [PHPSTAN](https://github.com/phpstan/phpstan) tool, you can run:


```bash
ddev phpstan /var/www/html/my-own-modules/crowdsec-php-lib/tools/coding-standards phpstan/phpstan.neon /var/www/html/my-own-modules/crowdsec-php-lib/src

```


##### PHP Mess Detector

To use the [PHPMD](https://github.com/phpmd/phpmd) tool, you can run:

```bash
ddev phpmd ./my-own-modules/crowdsec-php-lib/tools/coding-standards phpmd/rulesets.xml ../../src

```

##### PHPCS and PHPCBF

To use [PHP Code Sniffer](https://github.com/squizlabs/PHP_CodeSniffer) tools, you can run:

```bash
ddev phpcs ./my-own-modules/crowdsec-php-lib/tools/coding-standards my-own-modules/crowdsec-php-lib/src PSR12
```

and:

```bash
ddev phpcbf  ./my-own-modules/crowdsec-php-lib/tools/coding-standards my-own-modules/crowdsec-php-lib/src PSR12
```


##### PSALM

To use [PSALM](https://github.com/vimeo/psalm) tools, you can run:

```bash
ddev psalm ./my-own-modules/crowdsec-php-lib/tools/coding-standards ./my-own-modules/crowdsec-php-lib/tools/coding-standards/psalm
```

##### PHP Unit Code coverage

In order to generate a code coverage report, you have to:

- Enable `xdebug`:
```bash
ddev xdebug
```

To generate a html report, you can run:
```bash
ddev exec XDEBUG_MODE=coverage BOUNCER_KEY=your-bouncer-key  AGENT_TLS_PATH=/var/www/html/cfssl LAPI_URL=https://crowdsec:8080 REDIS_DSN=redis://redis:6379 MEMCACHED_DSN=memcached://memcached:11211  /usr/bin/php  ./my-own-modules/crowdsec-php-lib/tools/coding-standards/vendor/bin/phpunit  --configuration ./my-own-modules/crowdsec-php-lib/tools/coding-standards/phpunit/phpunit.xml

```

You should find the main report file `dashboard.html` in `tools/coding-standards/phpunit/code-coverage` folder.


If you want to generate a text report in the same folder:

```bash
ddev exec XDEBUG_MODE=coverage BOUNCER_KEY=your-bouncer-key LAPI_URL=https://crowdsec:8080
MEMCACHED_DSN=memcached://memcached:11211 REDIS_DSN=redis://redis:6379 /usr/bin/php  ./my-own-modules/crowdsec-php-lib/tools/coding-standards/vendor/bin/phpunit  --configuration ./my-own-modules/crowdsec-php-lib/tools/coding-standards/phpunit/phpunit.xml --coverage-text=./my-own-modules/crowdsec-php-lib/tools/coding-standards/phpunit/code-coverage/report.txt 
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

#### Redis debug

You should enter the `Redis` container:

```bash
ddev exec -s redis redis-cli
```

Then, you could play with the `redis-cli` command line tool:

- Display keys and databases: `INFO keyspace`

- Display stored keys:  `KEYS *`

- Display key value:    `GET [key]`

- Remove a key: `DEL [key]`

#### Memcached debug

@see https://lzone.de/cheat-sheet/memcached

First, find the IP of the `Memcached` container:

```bash
ddev find-ip memcached
```

Then, you could use `telnet` to interact with memcached:

```
telnet <MEMCACHED_IP> 11211
```

- `stats`

- `stats items`: The first number after `items` is the slab id. Request a cache dump for each slab id, with a limit for
the max number of keys to dump:

- `stats cachedump 2 100`

- `get <mykey>` : Read a value

- `delete <mykey>`: Delete a key


## Example scripts

You will find some php scripts in the `scripts` folder.

**N.B**. : If you are not using DDEV, you can replace all `ddev exec php ` by `php` and specify the right script paths.

### Clear cache script

To clear your LAPI cache, you can use the [`clear-php`](../scripts/clear-cache.php) script: 

```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/clear-cache.php <BOUNCER_KEY>
```

### Full Live mode example

This example demonstrates how the PHP Lib works with cache when you are using the live mode.

We will use here the [`standalone-check-ip-live.php`](../scripts/standalone-check-ip-live.php).

#### Set up the context

Start the containers:

```bash
ddev start
```

Then get a bouncer API key by copying the result of:

```bash
ddev create-bouncer
```

#### Get the remediation the clean IP "1.2.3.4"

Try with the `standalone-check-ip-live.php` file:


```bash
ddev exec php my-own-modules/crowdsec-php-lib/scripts/standalone-check-ip-live.php 1.2.3.4 <YOUR_BOUNCER_KEY>
```

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
ddev exec php my-own-modules/crowdsec-php-lib/scripts/standalone-check-ip-live.php 1.2.3.4 <YOUR_BOUNCER_KEY>
```

This is a ban (and cache miss) as you can see in your terminal logs.


## Discover the CrowdSec LAPI

This library interacts with a CrowdSec agent that you have installed on an accessible server.

The easiest way to interact with the local API (LAPI) is to use the `cscli` tool,but it is also possible to contact it
through a certain URL (e.g. `https://crowdsec:8080`).

### Use the CrowdSec cli (`cscli`)


Please refer to the [CrowdSec cscli documentation](https://docs.crowdsec.net/docs/cscli/cscli/) for an exhaustive
list of commands.

**N.B**.: If you are using DDEV, just replace `cscli` with `ddev exec -s crowdsec cscli`.

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
cscli decisions delete -i <SOME_IP>
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

Please see the [CrowdSec LAPI documentation](https://crowdsecurity.github.io/api_doc/index.html?urls.primaryName=LAPI) for an exhaustive list of available calls.

If you are using DDEV, you can enter the web by running:

```bash
ddev exec bash
````

Then, you should use some `curl` calls to contact the LAPI.

For example, you can get the list of decisions with commands like:

```bash
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" https://crowdsec:8080/v1/decisions | jq
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" https://crowdsec:8080/v1/decisions?ip=1.2.3.4 | jq
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" https://crowdsec:8080/v1/decisions/stream?startup=true | jq
curl -H "X-Api-Key: <YOUR_BOUNCER_KEY>" https://crowdsec:8080/v1/decisions/stream | jq
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


You can use the `commit-msg` git hook that you will find in the `.githooks` folder: 

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


Alternatively, you could use the [GitHub CLI](https://github.com/cli/cli): 
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

Note that the GitHub action will fail if the tag `tag_name` already exits.

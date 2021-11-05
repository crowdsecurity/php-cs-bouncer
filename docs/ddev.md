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
- Create an empty `.ddev` folder for DDEV and clone our pre-configured DDEV repo:

```
mkdir php-project-sources/.ddev && cd php-project-sources/.ddev && git clone git@github.com:julienloizelet/ddev-php.
git ./
```
- Copy some configurations file:

By default, ddev will launch a PHP 7.2 container. If you want to work with another PHP version, copy the 
corresponding config file. For example:

```
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
ddev exec BOUNCER_KEY=your-bouncer-key LAPI_URL=http://crowdsec:8080 MEMCACHED_DSN=memcached://memcached:11211 
REDIS_DSN=redis://redis:6379 /usr/bin/php ./my-own-modules/crowdsec-php-lib/vendor/bin/phpunit --testdox --colors --exclude-group ignore ./my-own-modules/crowdsec-php-lib/tests/IpVerificationTest.php
```
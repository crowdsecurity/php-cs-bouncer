![CrowdSec Logo](images/logo_crowdsec.png)
# CrowdSec Bouncer PHP library

## User Guide


<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Description](#description)
- [Prerequisites](#prerequisites)
- [Features](#features)
- [Usage](#usage)
  - [Create your own bouncer](#create-your-own-bouncer)
    - [Quick start](#quick-start)
    - [Ready to use PHP bouncers](#ready-to-use-php-bouncers)
  - [Auto Prepend File mode (standalone mode)](#auto-prepend-file-mode-standalone-mode)
    - [PHP](#php)
    - [Nginx](#nginx)
    - [Apache](#apache)
    - [Standalone settings](#standalone-settings)
    - [Test the standalone bouncer](#test-the-standalone-bouncer)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->


## Description

This library allows you to create CrowdSec bouncers for PHP applications or frameworks like e-commerce, blog or other 
exposed applications. It can also be used in a standalone mode using auto-prepend directive.

## Prerequisites

To be able to use this bouncer, the first step is to install [CrowdSec v1](https://doc.crowdsec.net/docs/getting_started/install_crowdsec/).
CrowdSec is only in charge of the "detection", and won't block anything on its own. You need to deploy a bouncer to "apply" decisions.

Please note that first and foremost a CrowdSec agent must be installed on a server that is accessible by this library.

## Features

- CrowdSec LAPI Support (CAPI not supported yet)
  - Handle IP, IP ranges and Country scoped decisions
  - Clear, prune and refresh the LAPI cache
  - `Live mode` or `Stream mode`
- Large PHP matrix compatibility: 7.2.x, 7.3.x, 7.4.x, 8.0.x and 8.1.x
- Built-in support for the most known cache systems like Redis, Memcached, PhpFiles
- Events logged using monolog
- Cap remediation level (ex: for sensitives websites: ban will be capped to captcha)



## Usage

When a user is suspected by CrowdSec to be malevolent, a bouncer will either send him/her a captcha to resolve or
simply a page notifying that access is denied. If the user is considered as a clean user, he will access the page as normal.

By default, the ban wall is displayed as below:

![Ban wall](images/screenshots/front-ban.jpg)

By default, the captcha wall is displayed as below:

![Captcha wall](images/screenshots/front-captcha.jpg)

Please note that it is possible to customize all the colors of these pages so that they integrate best with your design.

On the other hand, all texts are also fully customizable. This will allow you, for example, to present translated pages in your users' language.


### Create your own bouncer

You can use this library to develop your own PHP application bouncer.

#### Quick start

In your PHP project, just add these lines to verify an IP:

```php

<?php
use CrowdSecBouncer\Bouncer;

// Init bouncer
$bouncer = new Bouncer();
$bouncer->configure(['api_key' => 'YOUR_BOUNCER_API_KEY', 'api_url' => 'http://127.0.0.1:8080']);

// Ask remediation to API
$remediation = $bouncer->getRemediationForIp($requestedIp);
echo "\nResult: $remediation\n\n"; // "ban", "captcha" or "bypass"
```

#### Configurations

Please see the below [Standalone settings](#standalone-settings) paragraph or look at the [Settings example file](../scripts/auto-prepend/settings.example.php) for a description of each available parameter that you can pass to the `configure` method.


#### The `Standalone` example

This library includes the [`StandaloneBounce`](../src/StandaloneBounce.php) class. You can see that class as a good 
example for creating your own bouncer. This class extends [`AbstractBounce`](../src/AbstractBounce.php) and 
implements [IBounce](../src/IBounce.php). All bouncers should do the same. In order to add the bounce logic, you 
should first instantiate your bouncer:

```php
use \CrowdSecBouncer\StandaloneBounce
$bounce = new StandaloneBounce();
```
And then, you should initialize the bouncer by passing all the configuration array in a `init` method: 

```php
$configs = [...] // @See below for configuration details
$bouncer = $bounce->init($configs)
```

Finally, you can bounce by calling:

```php
$bouncer->run();
```

If you have implemented a `safelyBounce` method (like in [`StandaloneBounce`](../src/StandaloneBounce.php) class), 
you can just do:

```php
use \CrowdSecBouncer\StandaloneBounce
$bounce = new StandaloneBounce();
$configs = [...] // Retrieve configs from somewhere (database, static file, etc)
$bounce->safelyBounce($configs);
```



To go further and learn how to include this library in your
project, you should follow the [`DEVELOPER GUIDE`](DEVELOPER.md).

#### Ready to use PHP bouncers

To have a more concrete idea on how to develop a bouncer, you may look at the following bouncers for Magento 2 and 
WordPress :
- [CrowdSec Bouncer extension for Magento 2](https://github.com/crowdsecurity/cs-magento-bouncer)
- [CrowdSec Bouncer plugin for WordPress ](https://github.com/crowdsecurity/cs-wordpress-bouncer)


### Auto Prepend File mode (standalone mode)

This library can also be used on its own if you are running a server with PHP.

In this mode, every browser access to a php script will be bounced.

To enable the `auto prepend file` mode, you have to:


- copy the `scripts/auto-prepend/settings.example.php` to a `scripts/auto-prepend/settings.php` and fill the
  necessary settings in it (see below for the settings details).



- configure your server by adding an `auto_prepend_file` directive for your php setup.


Adding an `auto_prepend_file` directive can be done in different ways:

#### PHP

You should add this line to a `.ini` file :

    auto_prepend_file = /absolute/path/to/scripts/auto-prepend/bounce.php


#### Nginx


If you are using Nginx, you should modify your nginx configuration file by adding a `fastcgi_param`
directive. The php block should look like below:

```
location ~ \.php$ {
    ...
    ...
    ...
    fastcgi_param PHP_VALUE "/absolute/path/to/scripts/auto-prepend/bounce.php";
}
```

#### Apache

If you are using Apache, you should add this line to your `.htaccess` file:

    php_value auto_prepend_file "/absolute/path/to/scripts/auto-prepend/bounce.php"


#### Standalone settings

Once you have created the `scripts/auto-prepend/settings.php` file, you have to fill the necessary fields: 

```php
use CrowdSecBouncer\Constants;
$crowdSecStandaloneBouncerConfig = [
    /** The bouncer api key to access LAPI or CAPI.
     *
     * Key generated by the cscli (CrowdSec cli) command like "cscli bouncers add bouncer-php-library"
     */
    'api_key'=> 'YOUR_BOUNCER_API_KEY',

    /** Define the URL to your LAPI server, default to CAPI URL.
     *
     * If you have installed the CrowdSec agent on your server, it should be "http://localhost:8080"
     */
    'api_url'=> Constants::DEFAULT_LAPI_URL,

    // In seconds. The timeout when calling CAPI/LAPI. Must be greater or equal than 1. Defaults to 1 sec.
    'api_timeout'=> 1,

    // HTTP user agent used to call CAPI or LAPI. Default to this library name/current version.
    'api_user_agent'=> 'CrowdSec PHP Library/x.x.x',

    // true to enable verbose debug log.
    'debug_mode' => false,

    /** Absolute path to store log files.
     *
     * Important note: be sur this path won't be publicly accessible
     */
    'log_directory_path' => __DIR__.'/.logs',

    // true to stop the process and display errors if any.
    'display_errors' => false,

    /** Only for test or debug purpose. Default to empty.
     *
     * If not empty, it will be used for all remediation and geolocation processes.
     */
    'forced_test_ip' => '1.2.3.4',

    /** Select from 'bouncing_disabled', 'normal_bouncing' or 'flex_bouncing'.
     *
     * Choose if you want to apply CrowdSec directives (Normal bouncing) or be more permissive (Flex bouncing).
     * With the `Flex mode`, it is impossible to accidentally block access to your site to people who don’t
     * deserve it. This mode makes it possible to never ban an IP but only to offer a Captcha, in the worst-case
     * scenario.
     */
    'bouncing_level' => Constants::BOUNCING_LEVEL_NORMAL,

    /** Select from 'bypass' (minimum remediation), 'captcha' or 'ban' (maximum remediation).
     * Default to 'captcha'.
     *
     * Handle unknown remediations as.
     */
    'fallback_remediation'=> Constants::REMEDIATION_CAPTCHA,

    /** Select from 'bypass' (minimum remediation),'captcha' or 'ban' (maximum remediation).
     * Default to 'ban'.
     *
     * Cap the remediation to the selected one.
     */
    'max_remediation_level'=> Constants::REMEDIATION_BAN,

    /** If you use a CDN, a reverse proxy or a load balancer, set an array of IPs.
     *
     * For other IPs, the bouncer will not trust the X-Forwarded-For header.
     */
    'trust_ip_forward_array' => [],

    // Select from 'phpfs' (File system cache), 'redis' or 'memcached'.
    'cache_system' => Constants::CACHE_SYSTEM_PHPFS,

    /** Will be used only if you choose File system as cache_system
     *
     * Important note: be sur this path won't be publicly accessible
     */
    'fs_cache_path' => __DIR__.'/.cache',

    // Will be used only if you choose Redis cache as cache_system
    'redis_dsn' => 'redis://localhost:6379',

    // Will be used only if you choose Memcached as cache_system
    'memcached_dsn' => 'memcached://localhost:11211',

    // Set the duration we keep in cache the fact that an IP is clean. In seconds. Defaults to 5.
    'cache_expiration_for_clean_ip'=> Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,

    // Optional. Set the duration we keep in cache the fact that an IP is bad. In seconds. Defaults to 20.
    'cache_expiration_for_bad_ip'=> Constants::CACHE_EXPIRATION_FOR_BAD_IP,

    /** true to enable stream mode, false to enable the live mode. Default to false.
     *
     * By default, the `live mode` is enabled. The first time a stranger connects to your website, this mode
     * means that the IP will be checked directly by the CrowdSec API. The rest of your user’s browsing will be
     * even more transparent thanks to the fully customizable cache system.
     *
     * But you can also activate the `stream mode`. This mode allows you to constantly feed the bouncer with the
     * malicious IP list via a background task (CRON), making it to be even faster when checking the IP of your
     * visitors. Besides, if your site has a lot of unique visitors at the same time, this will not influence the
     * traffic to the API of your CrowdSec instance.
     */
    'stream_mode'=> false,

    // Settings for geolocation remediation (i.e. country based remediation).
    'geolocation' => [
        // true to enable remediation based on country. Default to false.
        'enabled' => false,
        // Geolocation system. Only 'maxmind' is available for the moment. Default to 'maxmind'
        'type' => Constants::GEOLOCATION_TYPE_MAXMIND,
        /** true to store the geolocalized country in session. Default to true.
         *
         * Setting true will avoid multiple call to the geolocalized system (e.g. maxmind database)
         */
        'save_in_session' => true,
        // MaxMind settings
        'maxmind' => [
            /**Select from 'country' or 'city'. Default to 'country'
             *
             * These are the two available MaxMind database types.
             */
            'database_type' => Constants::MAXMIND_COUNTRY,
            // Absolute path to the MaxMind database (mmdb file).
            'database_path' => '/some/path/GeoLite2-Country.mmdb',
        ]
    ],

    //true to hide CrowdSec mentions on ban and captcha walls.
    'hide_mentions' => false,

    // Settings for ban and captcha walls
    'theme_color_text_primary' => 'black',
    'theme_color_text_secondary' => '#AAA',
    'theme_color_text_button' => 'white',
    'theme_color_text_error_message' => '#b90000',
    'theme_color_background_page' => '#eee',
    'theme_color_background_container' => 'white',
    'theme_color_background_button' => '#626365',
    'theme_color_background_button_hover' => '#333',
    'theme_custom_css' => '',
    // Settings for captcha wall
    'theme_text_captcha_wall_tab_title' => 'Oops..',
    'theme_text_captcha_wall_title' => 'Hmm, sorry but...',
    'theme_text_captcha_wall_subtitle' => 'Please complete the security check.',
    'theme_text_captcha_wall_refresh_image_link' => 'refresh image',
    'theme_text_captcha_wall_captcha_placeholder' => 'Type here...',
    'theme_text_captcha_wall_send_button' => 'CONTINUE',
    'theme_text_captcha_wall_error_message' => 'Please try again.',
    'theme_text_captcha_wall_footer' => '',
    // Settings for ban wall
    'theme_text_ban_wall_tab_title' => 'Oops..',
    'theme_text_ban_wall_title' => '🤭 Oh!',
    'theme_text_ban_wall_subtitle' => 'This page is protected against cyber attacks and your IP has been banned by our system.',
    'theme_text_ban_wall_footer' => '',
];
```

#### Test the standalone bouncer

Now you can a decision to ban your own IP for 5 minutes to test the correct behavior:

```bash
cscli decisions add --ip <YOUR_IP> --duration 5m --type ban
```

You can also test a captcha:

```bash
cscli decisions delete --all # be careful with this command!
cscli decisions add --ip <YOUR_IP> --duration 15m --type captcha
```



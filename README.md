
<p align="center">
<img src="https://raw.githubusercontent.com/crowdsecurity/crowdsec/master/docs/assets/images/crowdsec_logo.png" alt="CrowdSec" title="CrowdSec" width="200" height="120"/>
</p>

# PHP Bouncer Library

> The official PHP bouncer library for the CrowdSec LAPI/CAPI

<p align="center">
<img src="https://img.shields.io/github/workflow/status/crowdsecurity/php-cs-bouncer/tests/main">
<img src="https://img.shields.io/github/license/crowdsecurity/php-cs-bouncer">
<img src="https://img.shields.io/github/v/release/crowdsecurity/php-cs-bouncer?include_prereleases">
</p>

<p align="center">
:books: <a href="https://doc.crowdsec.net">Documentation</a>
:diamond_shape_with_a_dot_inside: <a href="https://hub.crowdsec.net">Hub</a>
:speech_balloon: <a href="https://discourse.crowdsec.net">Discourse Forum</a>
:speech_balloon: <a href="https://gitter.im/crowdsec-project/community?utm_source=share-link&utm_medium=link&utm_campaign=share-link">Gitter Chat</a>
</p>

> This library allows you to create CrowdSec bouncers for PHP applications or frameworks like e-commerce, blog or other exposed applications.

## Features

- ✅ Fast API client
- ✅ LAPI Support (CAPI not supported yet)
- ✅ Built-in support for the most known cache systems like Redis, Memcached, PhpFiles
- ✅ **Live mode** or **Stream mode**
- ✅ Events logged using monolog
- ✅ Large PHP matrix compatibility: 7.2.x, 7.3.x, 7.4.x and 8.0.x
- ✅ Cap remediation level (ex: for sensitives websites: ban will be capped to captcha)
- ✅ Clear and prune the cache
## Getting started

### Installing CrowdSec Bouncer library

The recommended way to install CrowdSec Bouncer library is through [Composer](https://getcomposer.org/).

```bash
composer require crowdsec/bouncer
```

```php

/* To get a bouncer api key: "cscli bouncers add <name-of-your-php-bouncer> */
$bouncerApiKey = 'YOUR_BOUNCER_API_KEY';

/* Select the best cache adapter for your needs (Memcached, Redis, PhpFiles, ...) */
$cacheAdapter = new Symfony\Component\Cache\Adapter\PhpFilesAdapter();

$bouncer = new CrowdSecBouncer\Bouncer();
$bouncer->configure(['api_key'=> $bouncerApiKey], $cacheAdapter);

$remediation = $bouncer->getRemediationForIp($blockedIp);// Return "ban", "captcha" or "bypass"
```

View [`docs/getting-started.md`](https://github.com/crowdsecurity/php-cs-bouncer/blob/main/docs/getting-started.rst) to learn how to include this library in your project in minutes.

## Future
- Retrieve cache items with pagination
- Release 1.0.0 version
- Direct CAPI support
- Support more cache systems (Apcu, Couchbase, Doctrine, Pdo)
- Publish load tests (compare performances)
- Report Code coverage
- Setup Xdebug environment with Docker

## Licence

[MIT License](https://github.com/crowdsecurity/php-cs-bouncer/blob/main/LICENSE)

## Licence

[MIT License](https://github.com/crowdsecurity/php-cs-bouncer/blob/main/LICENSE)
<p align="center">
<img src="https://raw.githubusercontent.com/crowdsecurity/crowdsec-docs/main/docs/assets/images/crowdsec_logo.png" alt="CrowdSec" title="CrowdSec" width="200" height="120"/>
</p>

# PHP Bouncer Library

> The official PHP bouncer library for the CrowdSec LAPI/CAPI

![Version](https://img.shields.io/github/v/release/crowdsecurity/php-cs-bouncer?include_prereleases)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=crowdsecurity_php-cs-bouncer&metric=alert_status)](https://sonarcloud.io/dashboard?id=crowdsecurity_php-cs-bouncer)
![Build Status](https://img.shields.io/github/workflow/status/crowdsecurity/php-cs-bouncer/tests/main)
![Licence](https://img.shields.io/github/license/crowdsecurity/php-cs-bouncer)


<p align="center">
:books: <a href="https://doc.crowdsec.net">Documentation</a>
:diamond_shape_with_a_dot_inside: <a href="https://hub.crowdsec.net">Hub</a>
:speech_balloon: <a href="https://discourse.crowdsec.net">Discourse Forum</a>
:speech_balloon: <a href="https://gitter.im/crowdsec-project/community?utm_source=share-link&utm_medium=link&utm_campaign=share-link">Gitter Chat</a>
</p>

This library allows you to create CrowdSec bouncers for PHP applications or frameworks like e-commerce, blog or other exposed applications.

## Features

- ✅ Fast API client
- ✅ LAPI Support ([CAPI not supported yet](https://github.com/crowdsecurity/php-cs-bouncer#future))
- ✅ Built-in support for the most known cache systems like Redis, Memcached, PhpFiles
- ✅ **Live mode** or **Stream mode**
- ✅ Events logged using monolog
- ✅ Large PHP matrix compatibility: 7.2.x, 7.3.x, 7.4.x and 8.0.x
- ✅ Cap remediation level (ex: for sensitives websites: ban will be capped to captcha)
- ✅ Clear and prune the cache
## Getting started

The recommended way to install CrowdSec Bouncer library is through [Composer](https://getcomposer.org/).

```bash
composer require crowdsec/bouncer
```

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

View [`examples/live-mode/full-example-live-mode.php`](examples/live-mode/full-example-live-mode.php).

> You can also follow the [`docs/complete-guide.md`](docs/complete-guide.md) to learn how to include this library in your project in minutes.

## Future
- Retrieve decisions stored in cache using pagination
- Direct CAPI support (no LAPI required)
- Support more cache systems (Apcu, Couchbase, Doctrine -SQL or MongoDB-, Pdo...)
- Publish load tests (compare performances)
- Report Code coverage
- Setup Xdebug environment with Docker

## Licence

[MIT License](https://github.com/crowdsecurity/php-cs-bouncer/blob/main/LICENSE)

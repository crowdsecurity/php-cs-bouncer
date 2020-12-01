# PHP Bouncer Library

The official PHP client for the CrowdSec APIs (LAPI or CAPI).

This client helps to create CrowdSec bouncers for PHP applications or frameworks (e-commerce, blog, other apps...).

## Getting started!

View `docs/getting-started.md` to learn how to include this library in your project.

You will find the full documenation here: (...) TODO P2

# Sources

- https://thephp.website/en/issue/php-docker-quick-setup/

# Licence

MIT License. Details in the `./LICENSE` file.

# TODO

Features:
- [x] LAPI Support
- [x] Most popular Cache system support (Redis, Memcached, FileSystem, PhpFiles)
- [x] Rupture mode
- [x] Stream mode
- [ ] Cap remediation level (ex: for sensitives websites: ban will be capped to captcha)
- [ ] Direct CAPI support
- [ ] Log events using monolog
- [ ] PHP 5.6 retro compatibility (currenly PHP 7.2+)
- [ ] Retrieve cache items with pagination
- [ ] Release 1.0.0 version
- [ ] Support more cache systems (Apcu, Couchbase, Doctrine, Pdo)

Code:
- [x] Docker dev environment (Dockerized Crowdsec, Redis, Memcached, Composer, PHPUnit)
- [x] Test Driven Development
- [x] Static documentation
- [x] PHP Doc
- [x] Setup Xdebug environment
- [ ] Report Code coverage
- [ ] Report Performance tests
- [ ] CS fixer
- [ ] Setup CI
- [ ] Setup CD
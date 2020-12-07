# PHP Bouncer Library

The official PHP client for the CrowdSec APIs (LAPI or CAPI).

This client helps to create CrowdSec bouncers for PHP applications or frameworks (e-commerce, blog, other apps...).

## Getting started

View `docs/getting-started.md` to learn how to include this library in your project.

You will find the full documenation here: (...) TODO P2

# Sources

- https://thephp.website/en/issue/php-docker-quick-setup/

# Licence

MIT License. Details in the `./LICENSE` file.

# TODO

Features:
- [x] Fast API client
- [x] LAPI Support
- [x] Built-in support for the most known cache systems: Redis, Memcached, PhpFiles
- [x] Live mode
- [x] Stream mode
- [x] Log events using monolog
- [x] PHP compatibility with 7.2.x, 7.3.x, 7.4.x and 8.0.x
- [ ] Cap remediation level (ex: for sensitives websites: ban will be capped to captcha)
- [ ] Retrieve cache items with pagination
- [ ] Direct CAPI support
- [ ] Release 1.0.0 version
- [ ] Support more cache systems (Apcu, Couchbase, Doctrine, Pdo)

Code:
- [x] Docker dev environment (Dockerized Crowdsec, Redis, Memcached, PHP)
- [x] Continuous Integration (CI, includes Integration Tests and Super Linter)
- [x] Integration tests (with TDD)
- [x] Documented (Static documentation, PHP Doc)
- [ ] Continuous Delivery (CD)
- [ ] Load tests (compare performances)
- [ ] Report Code coverage
- [ ] Setup Xdebug environment

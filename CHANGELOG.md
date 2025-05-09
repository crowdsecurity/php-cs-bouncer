# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## SemVer public API

The [public API](https://semver.org/spec/v2.0.0.html#spec-item-1) of this library consists of all public or protected methods, properties and constants belonging to the `src` folder.

As far as possible, we try to adhere to [Symfony guidelines](https://symfony.com/doc/current/contributing/code/bc.html#working-on-symfony-code) when deciding whether a change is a breaking change or not.

---

## [4.3.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v4.3.0) - 2025-04-30
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v4.2.0...v4.3.0)


### Added

- Add `hasBaasUri` method to detect if the bouncer is connected to a Block As A Service Lapi
- Add `resetUsageMetrics` method to reset the usage metrics cache item

---

## [4.2.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v4.2.0) - 2025-01-31
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v4.1.0...v4.2.0)


### Changed

- Allow Monolog 3 package and Symfony 7 packages

---


## [4.1.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v4.1.0) - 2025-01-10
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v4.0.0...v4.1.0)


### Changed

- Do not save origins count when the bouncer does not bounce the IP, due to business logic. This avoids sending a
  "processed" usage metrics to the LAPI when the IP is not bounced at all.

---

## [4.0.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v4.0.0) - 2025-01-09
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v3.2.0...v4.0.0)


### Added

- Add `pushUsageMetrics` method to `AbstractBouncer` class
- Save origins count item in cache after a remediation has been applied

### Changed

- **Breaking change**: `AbstractBouncer::getRemediationForIp` method returns now an array with `remediation` and 
  `origin` keys.
- **Breaking change**: `$remediationEngine` params of `AbstractBouncer` constructor is now a `LapiRemediationEngine` instance
- **Breaking change**: `AbstractBouncer::getAppSecRemediationForIp` don't need `$remediationEngine` param anymore
- **Breaking change**: `AbstractBouncer::handleRemediation` requires a new `origin` param
- Update `crowdsec/remediation-engine` dependency to `v4.0.0`

### Removed

- **Breaking change**: Remove `bouncing_level` constants and configuration as it is now in `crowdsec/remediation-engine` package


---

## [3.2.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v3.2.0) - 2024-10-23
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v3.1.0...v3.2.0)


### Added

- Add protected `buildRequestRawBody` helper method to `AbstractBouncer` class

---

## [3.1.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v3.1.0) - 2024-10-18
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v3.0.0...v3.1.0)


### Changed

- Update `crowdsec/remediation-engine` dependency to `v3.5.0` (`appsec_max_body_size_kb` and 
  `appsec_body_size_exceeded_action` settings)


---

## [3.0.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v3.0.0) - 2024-10-04
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v2.2.0...v3.0.0)


### Added

- Add AppSec support
- Add `use_appsec` configuration

### Changed

- *Breaking change*: Add abstract methods that must be implemented to use AppSec:
    - `getRequestHost`
    - `getRequestHeaders`
    - `getRequestRawBody`
    - `getRequestUserAgent`
- `bounceCurrentIp` method asks for AppSec remediation if `use_appsec` is true and IP remediation is `bypass` 
- Update `crowdsec/common` dependency to `v2.3.0`
- Update `crowdsec/remediation-engine` dependency to `v3.4.0`

### Removed

- *Breaking change*: Remove `DEFAULT_LAPI_URL` constant as it already exists in `crowdsec/lapi-client` package


---

## [2.2.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v2.2.0) - 2024-06-20
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v2.1.0...v2.2.0)


### Changed

- Change the visibility of `AbstractBouncer::getBanHtml` and `AbstractBouncer::getCaptchaHtml` to `protected` to enable custom html rendering implementation


---


## [2.1.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v2.1.0) - 2023-12-14
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v2.0.0...v2.1.0)


### Changed

- Update `gregwar/captcha` from `1.2.0` to `1.2.1` and remove override fixes
- Update `crowdsec/common` dependency to `v2.2.0` (`api_connect_timeout` setting)
- Update `crowdsec/remediation-engine` dependency to `v3.3.0` (`api_connect_timeout` setting)


---

## [2.0.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v2.0.0) - 2023-04-13
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v1.4.0...v2.0.0)


### Changed

- Update `gregwar/captcha` from `1.1.9` to `1.2.0` and remove some override fixes

### Removed

- Remove all code about standalone bouncer

---


## [1.4.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v1.4.0) - 2023-03-30
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v1.3.0...v1.4.0)


### Changed
- Do not rotate log files of standalone bouncer

---


## [1.3.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v1.3.0) - 2023-03-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v1.2.0...v1.3.0)


### Changed
- Use `crowdsec/remediation-engine` `^3.1.1` instead of `^3.0.0`
- Use Redis and PhpFiles cache without cache tags

---


## [1.2.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v1.2.0) - 2023-03-09
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v1.1.1...v1.2.0)


### Changed
- Use `crowdsec/remediation-engine` `^3.0.0` instead of `^2.0.0`

### Added
- Add a script to prune cache with a cron job (Standalone bouncer)

---


## [1.1.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v1.1.1) - 2023-02-16
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v1.1.0...v1.1.1)

### Fixed
- Fix log messages for captcha remediation

---

## [1.1.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v1.1.0) - 2023-02-16
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v1.0.1...v1.1.0)

### Changed
- Add more log messages during bouncing process

---

## [1.0.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v1.0.1) - 2023-02-10
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v1.0.0...v1.0.1)

### Fixed
- Update `AbstractBouncer::testCacheConnection` method to throw an exception for Memcached if necessary


---

## [1.0.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v1.0.0) - 2023-02-03
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.36.0...v1.0.0)

### Changed
- Change version to `1.0.0`: first stable release
- Update `crowdsec/remediation-engine` to a new major version [2.0.0](https://github.com/crowdsecurity/php-remediation-engine/releases/tag/v2.0.0)
- Use `crowdsec/common` [package](https://github.com/crowdsecurity/php-common) as a dependency for code factoring

### Added

- Add public API declaration


---


## [0.36.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.36.0) - 2023-01-26
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.35.0...v0.36.0)

### Changed
- *Breaking changes*: All the code has been refactored to use `crowdsec/remediation-engine` package: 
  - Lot of public methods have been deleted or replaced by others
  - A bouncer should now extend an `AbstractBouncer` class and implements some abstract methods
  - Some settings names have been changed


---


## [0.35.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.35.0) - 2022-12-16
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.34.0...v0.35.0)

### Changed
- Set default timeout to 120 and allow negative value for unlimited timeout

---


## [0.34.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.34.0) - 2022-11-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.33.0...v0.34.0)

### Changed
- Do not cache bypass decision in stream mode
- Replace unauthorized chars by underscore `_` in cache key

### Added
- Add compatibility with PHP 8.2

### Fixed
- Fix decision duration parsing when it uses milliseconds

---


## [0.33.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.33.0) - 2022-11-10
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.32.0...v0.33.0)

### Changed
- Do not use tags for `memcached` as it is discouraged

### Fixed
- In stream mode, a clean IP decision (`bypass`) was not cached at all. The decision is now cached for ten years as expected

---

## [0.32.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.32.0) - 2022-09-29
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.31.0...v0.32.0)

### Changed
- Refactor for coding standards (PHPMD, PHPCS)

---

## [0.31.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.31.0) - 2022-09-23
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.30.0...v0.31.0)

### Changed
- Use Twig as template engine for ban and captcha walls

---

## [0.30.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.30.0) - 2022-09-22
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.29.0...v0.30.0)

### Changed
- Update `symfony/cache` and `symfony/config` dependencies requirement

---
## [0.29.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.29.0) - 2022-08-11
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.28.0...v0.29.0)

### Added
- Add TLS authentication feature

---
## [0.28.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.28.0) - 2022-08-04
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.27.0...v0.28.0)
### Changed
- *Breaking change*: Rename `ClientAbstract` class to `AbstractClient`
- Hide `api_key` in log

### Added
- Add `disable_prod_log` configuration 

---
## [0.27.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.27.0) - 2022-07-29
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.26.0...v0.27.0)
### Changed
- *Breaking change*: Modify `getBouncerInstance` and `init` signatures

### Fixed
- Fix wrongly formatted range scoped decision retrieving
- Fix cache updated decisions count
---
## [0.26.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.26.0) - 2022-07-28
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.25.0...v0.26.0)
### Changed
- *Breaking change*: Modify all constructors (`Bouncer`, `ApiCache`, `ApiClient`, `RestClient`) to use only 
  configurations and logger as parameters
- Use `shouldBounceCurrentIp` method of Standalone before bouncer instantiation
- *Breaking change*: Modify `initLogger` method
---
## [0.25.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.25.0) - 2022-07-22
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.24.0...v0.25.0)
### Added
- Add a `use_curl` setting to make LAPI rest requests with `cURL` instead of `file_get_contents`
---
## [0.24.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.24.0) - 2022-07-08
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.23.0...v0.24.0)
### Added
- Add a `configs` attribute to Bouncer class
---
## [0.23.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.23.0) - 2022-07-07
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.22.1...v0.23.0)
### Added
- Add test configuration to mock IPs and proxy behavior
---
## [0.22.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.22.1) - 2022-06-03
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.22.0...v0.22.1)
### Fixed
- Handle custom error handler for Memcached tag aware adapter
---
## [0.22.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.22.0) - 2022-06-02
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.21.0...v0.22.0)
### Added
- Add configurations for captcha and geolocation variables cache duration
### Changed
- *Breaking change*: Use cache instead of session to store captcha and geolocation variables
- *Breaking change*: Use symfony cache tag adapter
- Change `geolocation/save_in_session` setting into `geolocation/save_result`
### Fixed
- Fix deleted decision count during cache update
---
## [0.21.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.21.0) - 2022-04-15
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.20.1...v0.21.0)
### Changed
- Change allowed versions of `symfony/cache` package
---
## [0.20.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.20.1) - 2022-04-07
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.20.0...v0.20.1)
### Added
- Handle old lib version (`< 0.14.0`) settings values retro-compatibility for Standalone bouncer
### Fixed
- Fix `AbstractBounce:displayCaptchaWall` function

---
## [0.20.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.20.0) - 2022-03-31
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.19.0...v0.20.0)
### Changed
- Require a minimum of 1 for `clean_ip_cache_duration` and `bad_ip_cache_duration` settings
- Do not use session for geolocation if `save_in_session` setting is not true.
---
## [0.19.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.19.0) - 2022-03-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.18.0...v0.19.0)
### Added
- Add `excluded_uris` configuration to exclude some uris (was hardcoded to `/favicon.ico`)

### Changed
- Change the redirection after captcha resolution to `/` (was `$_SERVER['REQUEST_URI']'`)

### Fixed
- Fix Standalone bouncer session handling

---
## [0.18.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.18.0) - 2022-03-18
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.17.1...v0.18.0)
### Changed
- *Breaking change*: Change `trust_ip_forward_array` symfony configuration node to an array of array.
---
## [0.17.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.17.1) - 2022-03-17
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.17.0...v0.17.1)
### Removed
- Remove testing scripts for quality gate test

---
## [0.17.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.17.0) - 2022-03-17
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.16.0...v0.17.0)
### Changed
- *Breaking change*: Refactor some logic of important methods (`init`, `run`, `safelyBounce`, `getBouncerInstance`)
- *Breaking change*: Change the configurations' verification by using `symfony/config` logic whenever it is possible
- *Breaking change*: Change scripts path, name and content (specifically auto-prepend-file' scripts and settings)
- *Breaking change*: Change `IBounce` interface
- *Breaking change*: Rename `StandAloneBounce` class by `StandaloneBounce`
- Rewrite documentations

### Fixed
- Fix `api_timeout` configuration

### Removed
- Remove all unmaintained test and development docker files, sh scripts and associated documentation
- Remove `StandaloneBounce::isConfigValid` method as all is already checked

---
## [0.16.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.16.0) - 2022-03-10
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.15.0...v0.16.0)
### Added
- Add geolocation feature to get remediation from `Country` scoped decisions (using MaxMind databases)
- Add end-to-end tests GitHub action
- Add GitHub action to check links in markdown and update TOC

### Changed
- *Breaking change*: Remove `live_mode` occurrences and use `stream_mode` instead
- Change PHP scripts for testing examples (auto-prepend, cron)
- Update docs

### Fixed
- Fix debug log in `no-dev` environment
- Fix empty logs in Unit Tests
---
## [0.15.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.15.0) - 2022-02-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.14.0...v0.15.0)
### Added
- Add tests for PHP 8.1 (memcached is excluded)
- Add GitHub action for Release process
- Add `CHANGELOG.md`
### Changed
- Use `BouncerException` for some specific errors
### Fixed
- Fix auto-prepend script: set `debug_mode` and `display_errors` values before bouncer init
- Fix `gregwar/captcha` for PHP 8.1
- Fix BouncerException arguments in `set_error_handler` method

### Removed
- Remove `composer.lock` file

---
## [0.14.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.14.0) - 2021-11-18
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.13.3...v0.14.0)
### Changed
- *Breaking change*: Fix typo in constant name (`boucing`=> `bouncing`)
- Allow older versions of symfony config and monolog
- Split debug logic in 2 : debug and display
- Redirect if captcha is resolved
- Update doc and scripts
---
## [0.13.3](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.13.3) - 2021-09-21
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.13.2...v0.13.3)
### Fixed
- Fix session handling with standalone library
---
## [0.13.2](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.13.2) - 2021-08-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.13.1...v0.13.2)
### Added
- Handle invalid ip format
---
## [0.13.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.13.1) - 2021-07-01
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.13.0...v0.13.1)
### Changed
- Close php session after bouncing
---
## [0.13.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.13.0) - 2021-06-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.12.0...v0.13.0)
### Fixed
- Fix standalone mode
---
## [0.12.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.12.0) - 2021-06-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.11.0...v0.12.0)
### Added
- Add standalone mode
---
## [0.11.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.11.0) - 2021-06-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.10.0...v0.11.0)
### Added
- Add a `Bounce` class to simplify specific implementations
- Add a `Standalone` implementation of the `Bounce` class
---
## [0.10.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.10.0) - 2021-01-23
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.9.0...v0.10.0)
### Added
- Add Ipv6 support
---
## [0.9.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.9.0) - 2021-01-13
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.8.6...v0.9.0)
### Added
- Add custom remediation templates

---
## [0.8.6](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.8.6) - 2021-01-05
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.8.5...v0.8.6)
### Fixed
- Fix version bump

---
## [0.8.5](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.8.5) - 2021-01-05
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.8.4...v0.8.5)
### Fixed
- Fix memcached edge case with long duration cache (unwanted int to float conversion)
---
## [0.8.4](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.8.4) - 2020-12-26
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.8.3...v0.8.4)
### Fixed
- Fix fallback remediation

---
## [0.8.3](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.8.3) - 2020-12-24
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.8.2...v0.8.3)
### Changed
- Do not set expiration limits in stream mode
---
## [0.8.2](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.8.2) - 2020-12-23
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.8.1...v0.8.2)
### Fixed
- Fix release process
---
## [0.8.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.8.1) - 2020-12-22
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.8.0...v0.8.1)
### Fixed
- Fix release process
---
## [0.8.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.8.0) - 2020-12-22
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.7.0...v0.8.0)
### Added
- Add redis+memcached test connection
---
## [0.7.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.7.0) - 2020-12-22
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.6.0...v0.7.0)
### Added
- Make crowdsec mentions hidable
- Add phpcs
### Changed
- Update doc
- Make a lint pass
### Fixed
- Fix fallback remediation
---
## [0.6.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.6.0) - 2020-12-20
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.5.2...v0.6.0)
### Changed
- Remove useless dockerfiles
---
## [0.5.2](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.5.2) - 2020-12-19
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.5.1...v0.5.2)
### Changed
- Update docs
---
## [0.5.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.5.1) - 2020-12-19
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.5.0...v0.5.1)
### Changed
- Make a lint pass
---
## [0.5.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.5.0) - 2020-12-19
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.4.4...v0.5.0)
### Added
- Add cache expiration for bad ips
- Include the GregWar Captcha generation lib
- Build nice 403 and captcha templates
- Log captcha resolutions
### Changed
- Use the latest CrowdSec docker image
- Use the "context" psr log feature for all logs to allow them to be parsable.
- Remove useless predis dependence
---
## [0.4.4](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.4.4) - 2020-12-15
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.4.3...v0.4.4)
### Changed
- Improve logging
---
## [0.4.3](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.4.3) - 2020-12-13
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.4.2...v0.4.3)

### Changed
- Improve logging
---
## [0.4.2](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.4.2) - 2020-12-12
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.4.1...v0.4.2)

### Fixed
- Fix durations bug
---
## [0.4.1](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.4.1) - 2020-12-12
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.4.0...v0.4.1)

### Added
- Use GitHub flow
---
## [0.4.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.4.0) - 2020-12-12
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.3.0...v0.4.0)

### Added
- Add release drafter
- Reduce cache durations
- Add remediation fallback
---
## [0.3.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.3.0) - 2020-12-09
[_Compare with previous release_](https://github.com/crowdsecurity/php-cs-bouncer/compare/v0.2.0...v0.3.0)

### Added
- Set PHP Files cache adapter as default
- Replace phpdoc template with phpdocmd
- Improve documentation add examples and a complete guide.
- Auto warmup cache
---
## [0.2.0](https://github.com/crowdsecurity/php-cs-bouncer/releases/tag/v0.2.0) - 2020-12-08
### Added
- Initial release

# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).


## [0.14.0] - 2021-11-18
### Changed
- Allow older versions of symfony config and monolog
- Split debug logic in 2 : debug and display
- Redirect if captcha is resolved
- Fix typo in constant name (`boucing`=> `bouncing`)
- Update doc and scripts

## [0.13.3] - 2021-09-21
### Fixed
- Fix session handling with standalone library

## [0.13.2] - 2021-08-24
### Added
- Handle invalid ip format

## [0.13.1] - 2021-07-01
### Changed
- Close php session after bouncing

## [0.13.0] - 2021-06-24
### Fixed
- Fix standalone mode

## [0.12.0] - 2021-06-24
### Added
- Add standalone mode

## [0.11.0] - 2021-06-24
### Added
- Add a `Bounce` class to simplify specific implementations
- Add a `Standalone` implementation of the `Bounce` class

## [0.10.0] - 2021-01-23
### Added
- Add Ipv6 support

## [0.9.0] - 2021-01-13
### Added
- Add custom remediation templates


## [0.8.6] - 2021-01-05
### Fixed
- Fix version bump


## [0.8.5] - 2021-01-05
### Fixed
- Fix memcached edge case with long duration cache (unwanted int to float conversion)

## [0.8.4] - 2020-12-26
### Fixed
- Fix fallback remediation


## [0.8.3] - 2020-12-24
### Changed
- Do not set expiration limits in stream mode

## [0.8.2] - 2020-12-23
### Fixed
- Fix release process

## [0.8.1] - 2020-12-22
### Fixed
- Fix release process

## [0.8.0] - 2020-12-22
### Added
- Add redis+memcached test connection

## [0.7.0] - 2020-12-22
### Added
- Make crowdsec mentions hidable
- Add phpcs
### Changed
- Update doc
- Make a lint pass
### Fixed
- Fix fallback remediation

## [0.6.0] - 2020-12-20
### Changed
- Remove useless dockerfiles

## [0.5.2] - 2020-12-19
### Changed
- Update docs

## [0.5.1] - 2020-12-19
### Changed
- Make a lint pass

## [0.5.0] - 2020-12-19
### Added
- Add cache expiration for bad ips
- Include the GregWar Captcha generation lib
- Build nice 403 and captcha templates
- Log captcha resolutions
### Changed
- Use the latest CrowdSec docker image
- Use the "context" psr log feature for all logs to allow them to be parsable.
- Remove useless predis dependence

## [0.4.4] - 2020-12-15
### Changed
- Improve logging

## [0.4.3] - 2020-12-13
### Changed
- Improve logging

## [0.4.2] - 2020-12-12
### Fixed
- Fix durations bug

## [0.4.1] - 2020-12-12
### Added
- Use github flow

## [0.4.0] - 2020-12-12
### Added
- Add release drafter
- Reduce cache durations
- Add remediation fallback

## [0.3.0] - 2020-12-09
### Added
- Set PHP Files cache adapter as default
- Replace phpdoc template with phpdocmd
- Improve documentation add examples and a complete guide.
- Auto warmup cache

## [0.2.0] - 2020-12-08
### Added
- Initial release

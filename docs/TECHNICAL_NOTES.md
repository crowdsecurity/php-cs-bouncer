![CrowdSec Logo](images/logo_crowdsec.png)

# CrowdSec Bouncer PHP library

## Technical notes

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Why use *Symfony/Cache* and *Symfony/Config* component?](#why-use-symfonycache-and-symfonyconfig-component)
- [Why not using Guzzle?](#why-not-using-guzzle)
- [Why not using Swagger Codegen?](#why-not-using-swagger-codegen)
- [Which PHP compatibility matrix?](#which-php-compatibility-matrix)
  - [Why not PHP 5.6?](#why-not-php-56)
  - [Why not 7.0.x nor 7.1.x ?](#why-not-70x-nor-71x-)
  - [Memcached and PHP 8.x](#memcached-and-php-8x)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->


We explain here each important technical decision used to design this library.

## Why use *Symfony/Cache* and *Symfony/Config* component?

The Cache component is compatible with many cache systems.

The Config component provides several classes to help you find, load, combine, fill and validate configuration values of any kind, whatever their source may be (YAML, XML, INI files, or for instance a database). A great job done by this library, tested and maintained under LTS versions.

This library is tested and maintained under LTS versions.

## Why not using Guzzle?

The last Guzzle versions remove the User-Agent to prevent risks. Since LAPI or CAPI need a valid User-Agent, we can not use Guzzle to request CAPI/LAPI.

## Why not using Swagger Codegen?

We were not able to use this client with ease ex: impossible to get JSON data, it seems there is a bug with unserialization, we received an empty array.

## Which PHP compatibility matrix?

### Why not PHP 5.6?

Because this PHP version is no more supported since December 2018 (not even a security fix). Also, a lot of libraries are no more compatible with this version. We don't want to use an older version of these libraries because Composer can only install one version of each extension/package. So, being compatible with this old PHP version means to be not compatible with projects using a new version of these libraries.

### Why not 7.0.x nor 7.1.x ?

These PHP versions are not anymore maintained for security fixes since 2019. We encourage you a lot to upgrade your PHP version. You can view the [full list of PHP versions lifecycle](https://www.php.net/supported-versions.php).

To get a robust library and not provide security bug unmaintained, we use [components](https://packagist.org/packages/symfony/cache#v3.4.47) under [LTS versioning](https://symfony.com/releases/3.4).

The oldest PHP version compatible with these libraries is PHP 7.2.x.


### Memcached and PHP 8.x

In order to use Memcached with a PHP 8.x set up, you must have an installed version of the memcached php extension > 3.1.5. To check what is your current version, you could run :

`php -r "echo phpversion('memcached');"`


Technical decisions
===================

We explain here each important technical decision used to design this
libary.

We use Symfony/Cache component
------------------------------

This component is compatible with many cache systems.

This library is tested and maintained under LTS versions.

We use Symfony/Config component
-------------------------------

The Config component provides several classes to help you find, load,
combine, fill and validate configuration values of any kind, whatever
their source may be (YAML, XML, INI files, or for instance a database).
A great job done by this library, tested and maintained under LTS
versions.

We don't use Guzzle
-------------------

The last Guzzle versions remove the User Agent to prevent risks. Since
LAPI or CAPI need a valid User Agent, we can not use Guzzle to request
CAPI/LAPI.

We don't use Swagger Codegen
----------------------------

We were not able to use this client with easen ex: impossible to get
JSON data, it's seemd there is a bug with unserialization, we received
an empty array.

PHP 7.0+ compatibility
----------------------

Why not PHP 5.6?

Because this PHP version is no more supported since december 2018 (not even security fix!).
Also, a lot of libraries are no more compatible with this version.
We don't want to use older version of theses libraries because Composer can only install one version of each extension/package.
So, being compatible with this old PHP version means to be not compatible with projets using new version of these libraries.

Why not 7.0.x?

We have compatibility issues between php 7.0 and php 8.0:

- To get a rubust library, we use components under LTS versionning. The last version of these libraries compatible with 7.0.x.)(https://packagist.org/packages/symfony/cache#v3.4.47) are (no more maintained since Novembre 2020)(https://symfony.com/releases/3.4).
- Also, the last PHPUnit version compatible with php 7.0.x (PHPUnit 6.5.14) is no more compatible with PHP 8:
- (PHP 7.0 is no more support since jan. 2019)(https://www.php.net/eol.php)

```sh
Warning: Private methods cannot be final as they are never overridden by other classes in /app/vendor/phpunit/phpunit/src/Util/Configuration.php on line 176
```

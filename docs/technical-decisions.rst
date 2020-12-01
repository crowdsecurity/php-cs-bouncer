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
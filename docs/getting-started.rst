Getting started!
================

How to use this library in an existing PHP project using composer ?
Follow the guide!

In your existing project, install the library:

.. code-block:: sh

   $ composer install crowdsec/bouncer-library

Use the bouncer library (rupture mode)
--------------------------------------

.. code-block:: php


   use CrowdSecBouncer\Bouncer;
   use Symfony\Component\Cache\Adapter\FilesystemAdapter;

   $apiToken = getenv(DEFINE_YOUR_TOKEN);// Good practice: define this secret data in environment variables.

   // Select the best cache adapter for your needs (Memcached, Redis, Apcu, Filesystem, Doctrine, PhpFileSystem, Couchbase, Pdo...)
   // The full list is here: https://symfony.com/doc/current/components/cache.html#available-cache-adapters
   $cacheAdapter = new FilesystemAdapter(); 

   $bouncer = new Bouncer();
   $bouncer->configure(['api_token'=> $apiToken], $cacheAdapter);

   $remediation = $bouncer->getRemediationForIp($blockedIp);// Return "ban", "catpcha" or "bypass"

Use the bouncer library (steam mode)
------------------------------------

TODO P2
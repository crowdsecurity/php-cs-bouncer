Getting started!
================

How to use this library in an existing PHP project using composer ?
Follow the guide!

In your existing project, install the library:

.. code-block:: sh

   $ composer install crowdsec/bouncer-library

Use the bouncer library (live mode)
--------------------------------------

.. code-block:: php

   /* To get a token: "cscli bouncers add <name-of-your-php-bouncer> */
   $apiToken = 'YOUR_TOKEN';

   /* Select the best cache adapter for your needs (Memcached, Redis, PhpFiles, ...) */
   $cacheAdapter = new Symfony\Component\Cache\Adapter\PhpFilesAdapter();

   $bouncer = new CrowdSecBouncer\Bouncer();
   $bouncer->configure(['api_token'=> $apiToken], $cacheAdapter);

   $remediation = $bouncer->getRemediationForIp($blockedIp);// Return "ban", "captcha" or "bypass".

Note: You can try more cache system but we did not test them for now (Apcu, Filesystem, Doctrine, Couchbase, Pdo).
The full list is here: https://symfony.com/doc/current/components/cache.html#available-cache-adapters

Use the bouncer library (stream mode)
------------------------------------

TODO P2
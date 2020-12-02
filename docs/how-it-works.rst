TODO P2 reword this text.

Sync Strategy
-------------

To synchronize the blocklist with the API, there is two options:

A) Synchronous strategy: the 4x4 method
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

It works everywhere (ex: Wordpress).

It does not need cron-like system to work. When the program is executed,
after instantiating the library, just call the syncDecisions() method,
the cache system will execute or not the syncDecisions() method whether
the cache version is stale or not. If the library is call in a web
context, one request will be longer than other each time the cache must
revalidated.

B) Asynchronous strategy: The "Formula 1" method
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Very low latency to expect. You have to be able to call the
syncDecisions() method on your own. You have two options here.

The recommended method is to write a script and to configure a cron task
to call it periodically (be sure to set a period slightly inferior to
the expiration parameter).

If you don't have enough permissions to run a cron-like task, you can
try another technique: write a dedicated webpage callable by a tierce
service (you can find many free services on the web). In this case,
don't forget to limit the url access to the service only, by exemple
using a token or checking the IP range).

Remember to prune the cache by yourself!
----------------------------------------

Some cache pools do not include an automated mechanism for pruning
expired cache items. For example, the FilesystemAdapter cache does not
remove expired cache items until an item is explicitly requested and
determined to be expired, for example, via a call to
Psr\Cache\CacheItemPoolInterface::getItem. Under certain workloads, this
can cause stale cache entries to persist well past their expiration,
resulting in a sizable consumption of wasted disk or memory space from
excess, expired cache items.

This shortcoming has been solved through the introduction of
Symfony\Component\Cache\PruneableInterface, which defines the abstract
method prune(). The ChainAdapter, FilesystemAdapter, PdoAdapter, and
PhpFilesAdapter all implement this new interface, allowing manual
removal of stale cache items.

To do so, just type:

.. code-block:: php

   $cache->prune();

More info here:
https://symfony.com/doc/current/components/cache/cache_pools.html#pruning-cache-items

Remediations methods
--------------------

This library is able to process two kind of remediations: 403 and
Captcha (Choice between Gregwar/Captcha ou ReCaptcha V3).

Cache methods
-------------

We use the excellent Symfony Cache component to store IPs Blocklist.
Many cache system are supported (including File system, Memached, Redis,
APCu...). The full cache methods list is available `here`_.

TODO P2 Review this doc

.. _here: https://symfony.com/doc/3.4/components/cache.html
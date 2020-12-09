CrowdSecBouncer\Bouncer
===============The main Class of this package. This is the first entry point of any PHP Bouncers using this library.
* Class name:Bouncer
* Namespace:\CrowdSecBouncerProperties
----------
###loggerprivate  logger



* Visibility: **private**
###configprivate  config



* Visibility: **private**
###apiCacheprivate  apiCache



* Visibility: **private**
###maxRemediationLevelIndexprivate  maxRemediationLevelIndex



* Visibility: **private**Methods
-------
###__constructmixed CrowdSecBouncer\Bouncer::__construct(\Psr\Log\LoggerInterface logger, \CrowdSecBouncer\ApiCache apiCache)



* Visibility: **public**#### Arguments*logger **Psr\Log\LoggerInterface***apiCache **[CrowdSecBouncer\ApiCache](CrowdSecBouncer-ApiCache.md)**
###configuremixed CrowdSecBouncer\Bouncer::configure(array config, \Symfony\Component\Cache\Adapter\AbstractAdapter cacheAdapter)Configure this instance.



* Visibility: **public**#### Arguments*config **array***cacheAdapter **Symfony\Component\Cache\Adapter\AbstractAdapter**
###capRemediationLevelmixed CrowdSecBouncer\Bouncer::capRemediationLevel(string remediation)Cap the remediation to a fixed value given in configuration



* Visibility: **private**#### Arguments*remediation **string**
###getRemediationForIpstring CrowdSecBouncer\Bouncer::getRemediationForIp(string ip)Get the remediation for the specified IP. This method use the cache layer.

In live mode, when no remediation was found in cache,
the cache system will call the API to check if there is a decision.

* Visibility: **public**#### Arguments*ip **string**
###getDefault403Templatemixed CrowdSecBouncer\Bouncer::getDefault403Template()Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP.



* Visibility: **public**
###refreshBlocklistCachemixed CrowdSecBouncer\Bouncer::refreshBlocklistCache()Used in stream mode only.

This method should be called periodically (ex: crontab) in a asynchronous way to update the bouncer cache.

* Visibility: **public**
###clearCachemixed CrowdSecBouncer\Bouncer::clearCache()This method clear the full data in cache.



* Visibility: **public**
###pruneCachemixed CrowdSecBouncer\Bouncer::pruneCache()This method prune the cache: it removes all the expired cache items.



* Visibility: **public**
###loadPaginatedBlocklistFromCachemixed CrowdSecBouncer\Bouncer::loadPaginatedBlocklistFromCache(int page, int itemPerPage)Browse the remediations cache.



* Visibility: **public**#### Arguments*page **int***itemPerPage **int**
###loadPaginatedLogsmixed CrowdSecBouncer\Bouncer::loadPaginatedLogs(int page, int itemPerPage)Browse the bouncer technical logs.



* Visibility: **public**#### Arguments*page **int***itemPerPage **int**
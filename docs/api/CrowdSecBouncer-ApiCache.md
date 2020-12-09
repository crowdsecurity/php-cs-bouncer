CrowdSecBouncer\ApiCache
===============The cache mecanism to store every decisions from LAPI/CAPI. Symfony Cache component powered.
* Class name:ApiCache
* Namespace:\CrowdSecBouncerProperties
----------
###loggerprivate  logger



* Visibility: **private**
###adapterprivate  adapter



* Visibility: **private**
###liveModeprivate  liveMode



* Visibility: **private**
###cacheExpirationForCleanIpprivate  cacheExpirationForCleanIp



* Visibility: **private**
###apiClientprivate  apiClient



* Visibility: **private**
###warmedUpprivate  warmedUp



* Visibility: **private**Methods
-------
###__constructmixed CrowdSecBouncer\ApiCache::__construct(\CrowdSecBouncer\ApiClient apiClient, \Psr\Log\LoggerInterface logger)



* Visibility: **public**#### Arguments*apiClient **[CrowdSecBouncer\ApiClient](CrowdSecBouncer-ApiClient.md)***logger **Psr\Log\LoggerInterface**
###configuremixed CrowdSecBouncer\ApiCache::configure(?\Symfony\Component\Cache\Adapter\AbstractAdapter adapter, bool liveMode, string apiUrl, int timeout, string userAgent, string apiKey, int cacheExpirationForCleanIp)Configure this instance.



* Visibility: **public**#### Arguments*adapter **?\Symfony\Component\Cache\Adapter\AbstractAdapter***liveMode **bool***apiUrl **string***timeout **int***userAgent **string***apiKey **string***cacheExpirationForCleanIp **int**
###addRemediationToCacheItemmixed CrowdSecBouncer\ApiCache::addRemediationToCacheItem(string ip, string type, int expiration, int decisionId)Add remediation to a Symfony Cache Item identified by IP



* Visibility: **private**#### Arguments*ip **string***type **string***expiration **int***decisionId **int**
###removeDecisionFromRemediationItemmixed CrowdSecBouncer\ApiCache::removeDecisionFromRemediationItem(string ip, int decisionId)Remove a decision from a Symfony Cache Item identified by ip



* Visibility: **private**#### Arguments*ip **string***decisionId **int**
###parseDurationToSecondsmixed CrowdSecBouncer\ApiCache::parseDurationToSeconds(string duration)Parse "duration" entries returned from API to a number of seconds.

TODO P3 TEST
9999h59m56.603445s
10m33.3465483s
33.3465483s
-285.876962ms
33s'// should break!;

* Visibility: **private*** This method is **static**.#### Arguments*duration **string**
###formatRemediationFromDecisionmixed CrowdSecBouncer\ApiCache::formatRemediationFromDecision(?array decision)Format a remediation item of a cache item.

This format use a minimal amount of data allowing less cache data consumption.

TODO P3 TESTS

* Visibility: **private**#### Arguments*decision **?array**
###defferUpdateCacheConfigmixed CrowdSecBouncer\ApiCache::defferUpdateCacheConfig(array config)



* Visibility: **private**#### Arguments*config **array**
###saveRemediationsmixed CrowdSecBouncer\ApiCache::saveRemediations(array decisions)Update the cached remediations from these new decisions.



* Visibility: **private**#### Arguments*decisions **array**
###removeRemediationsmixed CrowdSecBouncer\ApiCache::removeRemediations(array decisions)



* Visibility: **private**#### Arguments*decisions **array**
###saveRemediationsForIpmixed CrowdSecBouncer\ApiCache::saveRemediationsForIp(array decisions, string ip)Update the cached remediation of the specified IP from these new decisions.



* Visibility: **private**#### Arguments*decisions **array***ip **string**
###clearmixed CrowdSecBouncer\ApiCache::clear()



* Visibility: **public**
###warmUpmixed CrowdSecBouncer\ApiCache::warmUp()Used in stream mode only.

Warm the cache up.
Used when the stream mode has just been activated.

* Visibility: **private**
###pullUpdatesmixed CrowdSecBouncer\ApiCache::pullUpdates()Used in stream mode only.

Pull decisions updates from the API and update the cached remediations.
Used for the stream mode when we have to update the remediations list.

TODO P2 test for overlapping decisions strategy (ex: max expires)

* Visibility: **public**
###missmixed CrowdSecBouncer\ApiCache::miss(string ip)This method is called when nothing has been found in cache for the requested IP.

In live mode is enabled, calls the API for decisions concerning the specified IP
In stream mode, as we considere cache is the single source of truth, the IP is considered clean.
Finally the result is stored in caches for further calls.

* Visibility: **private**#### Arguments*ip **string**
###hitmixed CrowdSecBouncer\ApiCache::hit(string ip)Used in both mode (stream and ruptue).

This method formats the cached item as a remediation.
It returns the highest remediation level found.

* Visibility: **private**#### Arguments*ip **string**
###getstring CrowdSecBouncer\ApiCache::get(string ip)Request the cache for the specified IP.



* Visibility: **public**#### Arguments*ip **string**
###prunemixed CrowdSecBouncer\ApiCache::prune()



* Visibility: **public**
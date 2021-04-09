# CrowdSecBouncer\ApiCache  

The cache mecanism to store every decisions from LAPI/CAPI. Symfony Cache component powered.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#apicache__construct)||
|[clear](#apicacheclear)||
|[configure](#apicacheconfigure)|Configure this instance.|
|[get](#apicacheget)|Request the cache for the specified IP.|
|[prune](#apicacheprune)|Prune the cache (only when using PHP File System cache).|
|[pullUpdates](#apicachepullupdates)|Used in stream mode only.|
|[testConnection](#apicachetestconnection)|Test the connection to the cache system (Redis or Memcached).|
|[warmUp](#apicachewarmup)|Used in stream mode only.|




### ApiCache::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ApiCache::clear  

**Description**

```php
 clear (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ApiCache::configure  

**Description**

```php
public configure (bool $liveMode, string $apiUrl, int $timeout, string $userAgent, string $apiKey, int $cacheExpirationForCleanIp, int $cacheExpirationForBadIp, string $fallbackRemediation)
```

Configure this instance. 

 

**Parameters**

* `(bool) $liveMode`
: If we use the live mode (else we use the stream mode)  
* `(string) $apiUrl`
: The URL of the LAPI  
* `(int) $timeout`
: The timeout well calling LAPI  
* `(string) $userAgent`
: The user agent to use when calling LAPI  
* `(string) $apiKey`
: The Bouncer API Key to use to connect LAPI  
* `(int) $cacheExpirationForCleanIp`
: The duration to cache an IP considered as clean by LAPI  
* `(int) $cacheExpirationForBadIp`
: The duration to cache an IP considered as bad by LAPI  
* `(string) $fallbackRemediation`
: The remediation to use when the remediation sent by LAPI is not supported by this library  

**Return Values**

`void`


<hr />


### ApiCache::get  

**Description**

```php
public get (void)
```

Request the cache for the specified IP. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`string`

> the computed remediation string, or null if no decision was found


<hr />


### ApiCache::prune  

**Description**

```php
public prune (void)
```

Prune the cache (only when using PHP File System cache). 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ApiCache::pullUpdates  

**Description**

```php
public pullUpdates (void)
```

Used in stream mode only. 

Pull decisions updates from the API and update the cached remediations.  
Used for the stream mode when we have to update the remediations list. 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`

> number of deleted and new decisions, and errors when processing decisions


<hr />


### ApiCache::testConnection  

**Description**

```php
public testConnection (void)
```

Test the connection to the cache system (Redis or Memcached). 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


**Throws Exceptions**


`\BouncerException`
> if the connection was not successful

<hr />


### ApiCache::warmUp  

**Description**

```php
public warmUp (void)
```

Used in stream mode only. 

Warm the cache up.  
Used when the stream mode has just been activated. 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`

> "count": number of decisions added, "errors": decisions not added


<hr />


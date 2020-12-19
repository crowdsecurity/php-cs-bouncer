# CrowdSecBouncer\ApiCache  

The cache mecanism to store every decisions from LAPI/CAPI. Symfony Cache component powered.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#apicache__construct)||
|[clear](#apicacheclear)||
|[configure](#apicacheconfigure)|Configure this instance.|
|[get](#apicacheget)|Request the cache for the specified IP.|
|[prune](#apicacheprune)||
|[pullUpdates](#apicachepullupdates)|Used in stream mode only.|
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
public configure (void)
```

Configure this instance. 

 

**Parameters**

`This function has no parameters.`

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
 prune (void)
```

 

 

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

> number of deleted and new decisions.


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

`int`

> number of decisions added.


<hr />


# CrowdSecBouncer\Bouncer  

The main Class of this package. This is the first entry point of any PHP Bouncers using this library.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#bouncer__construct)||
|[clearCache](#bouncerclearcache)|This method clear the full data in cache.|
|[configure](#bouncerconfigure)|Configure this instance.|
|[getAccessForbiddenHtmlTemplate](#getAccessForbiddenHtmlTemplate)|Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP.|
|[getRemediationForIp](#bouncergetremediationforip)|Get the remediation for the specified IP. This method use the cache layer.|
|[loadPaginatedBlocklistFromCache](#bouncerloadpaginatedblocklistfromcache)|Browse the remediations cache.|
|[loadPaginatedLogs](#bouncerloadpaginatedlogs)|Browse the bouncer technical logs.|
|[pruneCache](#bouncerprunecache)|This method prune the cache: it removes all the expired cache items.|
|[refreshBlocklistCache](#bouncerrefreshblocklistcache)|Used in stream mode only.|




### Bouncer::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::clearCache  

**Description**

```php
public clearCache (void)
```

This method clear the full data in cache. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::configure  

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


### Bouncer::get403Template  

**Description**

```php
public getAccessForbiddenHtmlTemplate (void)
```

Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::getRemediationForIp  

**Description**

```php
public getRemediationForIp (void)
```

Get the remediation for the specified IP. This method use the cache layer. 

In live mode, when no remediation was found in cache,  
the cache system will call the API to check if there is a decision. 

**Parameters**

`This function has no parameters.`

**Return Values**

`string`

> the remediation to apply (ex: 'ban', 'captcha', 'bypass')


<hr />


### Bouncer::loadPaginatedBlocklistFromCache  

**Description**

```php
public loadPaginatedBlocklistFromCache (void)
```

Browse the remediations cache. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::loadPaginatedLogs  

**Description**

```php
public loadPaginatedLogs (void)
```

Browse the bouncer technical logs. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::pruneCache  

**Description**

```php
public pruneCache (void)
```

This method prune the cache: it removes all the expired cache items. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::refreshBlocklistCache  

**Description**

```php
public refreshBlocklistCache (void)
```

Used in stream mode only. 

This method should be called periodically (ex: crontab) in a asynchronous way to update the bouncer cache. 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


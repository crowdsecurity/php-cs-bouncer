# CrowdSecBouncer\Bouncer  

The main Class of this package. This is the first entry point of any PHP Bouncers using this library.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#bouncer__construct)||
|[buildCaptchaCouple](#bouncerbuildcaptchacouple)||
|[checkCaptcha](#bouncercheckcaptcha)||
|[clearCache](#bouncerclearcache)|This method clear the full data in cache.|
|[configure](#bouncerconfigure)|Configure this instance.|
|[getAccessForbiddenHtmlTemplate](#bouncergetaccessforbiddenhtmltemplate)|Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP.|
|[getCaptchaHtmlTemplate](#bouncergetcaptchahtmltemplate)|Returns a default "CrowdSec Captcha" HTML template to display to a web browser using a captchable IP.|
|[getLogger](#bouncergetlogger)|Returns the logger instance.|
|[getRemediationForIp](#bouncergetremediationforip)|Get the remediation for the specified IP. This method use the cache layer.|
|[pruneCache](#bouncerprunecache)|This method prune the cache: it removes all the expired cache items.|
|[refreshBlocklistCache](#bouncerrefreshblocklistcache)|Used in stream mode only.|
|[testConnection](#bouncertestconnection)|Test the connection to the cache system (Redis or Memcached).|
|[warmBlocklistCacheUp](#bouncerwarmblocklistcacheup)|Used in stream mode only.|




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


### Bouncer::buildCaptchaCouple  

**Description**

```php
 buildCaptchaCouple (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::checkCaptcha  

**Description**

```php
 checkCaptcha (void)
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


### Bouncer::getAccessForbiddenHtmlTemplate  

**Description**

```php
public static getAccessForbiddenHtmlTemplate (void)
```

Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP. 

The input $config should match the TemplateConfiguration input format. 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::getCaptchaHtmlTemplate  

**Description**

```php
public static getCaptchaHtmlTemplate (void)
```

Returns a default "CrowdSec Captcha" HTML template to display to a web browser using a captchable IP. 

The input $config should match the TemplateConfiguration input format. 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Bouncer::getLogger  

**Description**

```php
public getLogger (void)
```

Returns the logger instance. 

 

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

`array`

> number of deleted and new decisions


<hr />


### Bouncer::testConnection  

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


### Bouncer::warmBlocklistCacheUp  

**Description**

```php
public warmBlocklistCacheUp (void)
```

Used in stream mode only. 

This method should be called only to force a cache warm up. 

**Parameters**

`This function has no parameters.`

**Return Values**

`int`

> number of decisions added


<hr />


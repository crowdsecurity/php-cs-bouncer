# CrowdSecBouncer\Bouncer  

The main Class of this package. This is the first entry point of any PHP Bouncers using this library.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#bouncer__construct)||
|[buildCaptchaCouple](#bouncerbuildcaptchacouple)|Build a captcha couple.|
|[checkCaptcha](#bouncercheckcaptcha)|Check if the captcha filled by the user is correct or not.|
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
public static buildCaptchaCouple (void)
```

Build a captcha couple. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`

> an array composed of two items, a "phrase" string representing the phrase and a "inlineImage" representing the image data


<hr />


### Bouncer::checkCaptcha  

**Description**

```php
public checkCaptcha (string $expected, string $expected, string $ip)
```

Check if the captcha filled by the user is correct or not. 

We are premissive with the user (0 is interpreted as "o" and 1 in interpretted as "l"). 

**Parameters**

* `(string) $expected`
: The expected phrase  
* `(string) $expected`
: The phrase to check (the user input)  
* `(string) $ip`
: Th IP of the use (for logging purpose)  

**Return Values**

`bool`

> If the captcha input was correct or not


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

`bool`

> If the cache has been successfully cleared or not


<hr />


### Bouncer::configure  

**Description**

```php
public configure (array $config)
```

Configure this instance. 

 

**Parameters**

* `(array) $config`
: An array with all configuration parameters  

**Return Values**

`void`


<hr />


### Bouncer::getAccessForbiddenHtmlTemplate  

**Description**

```php
public static getAccessForbiddenHtmlTemplate (array $config)
```

Returns a default "CrowdSec 403" HTML template to display to a web browser using a banned IP. 

The input $config should match the TemplateConfiguration input format. 

**Parameters**

* `(array) $config`
: An array of template configuration parameters  

**Return Values**

`string`

> The HTML compiled template


<hr />


### Bouncer::getCaptchaHtmlTemplate  

**Description**

```php
public static getCaptchaHtmlTemplate (array $config)
```

Returns a default "CrowdSec Captcha" HTML template to display to a web browser using a captchable IP. 

The input $config should match the TemplateConfiguration input format. 

**Parameters**

* `(array) $config`
: An array of template configuration parameters  

**Return Values**

`string`

> The HTML compiled template


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

`\LoggerInterface`

> the logger used by this library


<hr />


### Bouncer::getRemediationForIp  

**Description**

```php
public getRemediationForIp (string $ip)
```

Get the remediation for the specified IP. This method use the cache layer. 

In live mode, when no remediation was found in cache,  
the cache system will call the API to check if there is a decision. 

**Parameters**

* `(string) $ip`
: The IP to check  

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

`bool`

> If the cache has been successfully pruned or not


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

> Number of deleted and new decisions, and errors when processing decisions


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

`bool`

> If the connection was successful or not


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

`array`

> "count": number of decisions added, "errors": decisions not added


<hr />


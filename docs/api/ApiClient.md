# CrowdSecBouncer\ApiClient  

The LAPI/CAPI REST Client. This is used to retrieve decisions.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#apiclient__construct)||
|[configure](#apiclientconfigure)|Configure this instance.|
|[getFilteredDecisions](#apiclientgetfiltereddecisions)|Request decisions using the specified $filter array.|
|[getStreamedDecisions](#apiclientgetstreameddecisions)|Request decisions using the stream mode. When the $startup flag is used, all the decisions are returned.|




### ApiClient::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ApiClient::configure  

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


### ApiClient::getFilteredDecisions  

**Description**

```php
public getFilteredDecisions (void)
```

Request decisions using the specified $filter array. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ApiClient::getStreamedDecisions  

**Description**

```php
public getStreamedDecisions (void)
```

Request decisions using the stream mode. When the $startup flag is used, all the decisions are returned. 

Else only the decisions updates (add or remove) from the last stream call are returned. 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


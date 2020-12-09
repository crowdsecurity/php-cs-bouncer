CrowdSecBouncer\ApiClient
===============The LAPI/CAPI REST Client. This is used to retrieve decisions.
* Class name:ApiClient
* Namespace:\CrowdSecBouncerProperties
----------
###loggerprivate  logger



* Visibility: **private**
###restClientprivate  restClient



* Visibility: **private**Methods
-------
###__constructmixed CrowdSecBouncer\ApiClient::__construct(\Psr\Log\LoggerInterface logger)



* Visibility: **public**#### Arguments*logger **Psr\Log\LoggerInterface**
###configuremixed CrowdSecBouncer\ApiClient::configure(string baseUri, int timeout, string userAgent, string apiKey)Configure this instance.



* Visibility: **public**#### Arguments*baseUri **string***timeout **int***userAgent **string***apiKey **string**
###getFilteredDecisionsmixed CrowdSecBouncer\ApiClient::getFilteredDecisions(array filter)Request decisions using the specified $filter array.



* Visibility: **public**#### Arguments*filter **array**
###getStreamedDecisionsmixed CrowdSecBouncer\ApiClient::getStreamedDecisions(bool startup)Request decisions using the stream mode. When the $startup flag is used, all the decisions are returned.

Else only the decisions updates (add or remove) from the last stream call are returned.

* Visibility: **public**#### Arguments*startup **bool**
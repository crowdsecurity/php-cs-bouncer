CrowdSecBouncer\RestClient
===============The low level REST Client.
* Class name:RestClient
* Namespace:\CrowdSecBouncerProperties
----------
###headerStringprivate  headerString



* Visibility: **private**
###timeoutprivate  timeout



* Visibility: **private**
###baseUriprivate  baseUri



* Visibility: **private**
###loggerprivate  logger



* Visibility: **private**Methods
-------
###__constructmixed CrowdSecBouncer\RestClient::__construct(\Psr\Log\LoggerInterface logger)



* Visibility: **public**#### Arguments*logger **Psr\Log\LoggerInterface**
###configuremixed CrowdSecBouncer\RestClient::configure(string baseUri, array headers, int timeout)Configure this instance.



* Visibility: **public**#### Arguments*baseUri **string***headers **array***timeout **int**
###convertHeadersToStringmixed CrowdSecBouncer\RestClient::convertHeadersToString(array headers)Convert an key-value array of headers to the official HTTP header string.



* Visibility: **private**#### Arguments*headers **array**
###requestmixed CrowdSecBouncer\RestClient::request(string endpoint, array queryParams, array bodyParams, string method, array headers, int timeout)Send an HTTP request using the file_get_contents and parse its JSON result if any.



* Visibility: **public**#### Arguments*endpoint **string***queryParams **array***bodyParams **array***method **string***headers **array***timeout **int**
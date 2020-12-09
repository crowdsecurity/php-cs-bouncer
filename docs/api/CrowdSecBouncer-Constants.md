CrowdSecBouncer\Constants
===============Every constants of the library are set here.
* Class name:Constants
* Namespace:\CrowdSecBouncerConstants
----------
###CAPI_URLconst CAPI_URL = 'https://api.crowdsec.net/v2/'
###BASE_USER_AGENTconst BASE_USER_AGENT = 'PHP CrowdSec Bouncer/1.0.0'
###API_TIMEOUTconst API_TIMEOUT = 1
###CACHE_EXPIRATION_FOR_CLEAN_IPconst CACHE_EXPIRATION_FOR_CLEAN_IP = 600
###REMEDIATION_BANconst REMEDIATION_BAN = 'ban'
###REMEDIATION_CAPTCHAconst REMEDIATION_CAPTCHA = 'captcha'
###REMEDIATION_BYPASSconst REMEDIATION_BYPASS = 'bypass'
###ORDERED_REMEDIATIONSconst ORDERED_REMEDIATIONS = [self::REMEDIATION_BAN, self::REMEDIATION_CAPTCHA, self::REMEDIATION_BYPASS]
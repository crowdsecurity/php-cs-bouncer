# Full configuration reference

```bash
   $config = [
       // Required. The bouncer api key to access LAPI or CAPI.
       'api_key'=> 'YOUR_BOUNCER_API_KEY',

       // Optional. Define the URL to your LAPI server, default to CAPI URL.
       'api_url'=> 'https://api.crowdsec.net/v2/',

       // Optional. HTTP user agent used to call CAPI or LAPI. Default to this library name/current version.
       'api_user_agent'=> 'CrowdSec PHP Library/x.x.x',

       // Optional. In seconds. The timeout when calling CAPI/LAPI. Defaults to 2 sec.
       'api_timeout'=> 2,

       // Optional. true to enable live mode, false to enable the stream mode. Default to true.
       'live_mode'=> true,
       
       // Optional. Cap the remediation to the selected one. Select from 'bypass' (minimum remediation), 'captcha' or 'ban' (maximum remediation). Defaults to 'ban'.
       'max_remediation_level'=> 'ban',

       // Optional. Set the duration we keep in cache the fact that an IP is clean. In seconds. Defaults to 600 (10 minutes).
       'cache_expiration_for_clean_ip'=> '600',
   ]
   $cacheAdapter = (...)
   $bouncer = new Bouncer();
   $bouncer->configure($config, $cacheAdapter);
```
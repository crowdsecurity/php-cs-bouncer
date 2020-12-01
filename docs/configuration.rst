Full configuration reference
----------------------------

.. code-block:: sh

   $config = [
       // Required. The token to access LAPI or CAPI.
       'api_token'=> 'YOUR_TOKEN',

       // Optional. Define the url to your LAPI server, defaults to CAPI URL.
       'api_url'=> 'https://api.crowdsec.net/v2/',

       // Optional. HTTP user agent used to call CAPI or LAPI. Defaults to this library name/current version.
       'api_user_agent'=> 'CrowdSec PHP Library/x.x.x',

       // Optional. In seconds. The timeout when calling CAPI/LAPI. Defaults to 2 sec.
       'api_timeout'=> 2,

       // Optional. true to enable rupture mode, false to enable the stream mode. Default to true.
       'rupture_mode'=> true,
       
       // Optional. Cap the remediation to the selected one. Select from 'bypass' (minimum remediation), 'captcha' or 'ban' (maximum remediation). Defaults to 'ban'.
       'max_remediation'=> 'ban',
   ]
   $cacheAdapter = (...)
   $bouncer = new Bouncer();
   $bouncer->configure($config, $cacheAdapter);
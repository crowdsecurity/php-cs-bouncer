# Full configuration reference

```php
   $config = [
       // Required. The bouncer api key to access LAPI or CAPI.
       'api_key'=> 'YOUR_BOUNCER_API_KEY',

       // Optional. Define the URL to your LAPI server, default to CAPI URL.
       'api_url'=> 'https://api.crowdsec.net/v2/',

       // Optional. HTTP user agent used to call CAPI or LAPI. Default to this library name/current version.
       'api_user_agent'=> 'CrowdSec PHP Library/x.x.x',

       // Optional. In seconds. The timeout when calling CAPI/LAPI. Defaults to 1 sec.
       'api_timeout'=> 1,
       
       // Optional. Select from 'bouncing_disabled', 'normal_bouncing' or 'flex_bouncing'. Default to 'normal_bouncing'
       'bouncing_level' => 'normal_bouncing',
       
       // Optional. Absolute path to store log files.
       'log_directory_path' => __DIR__.'/.logs',
       
       // Optional. Select from 'phpfs' (File system cache), 'redis' or 'memcached'. Default to 'phpcs'
       'cache_system' => 'phpfs',
       
       // Optional. Will be used only if you choose File system as cache_system
       'fs_cache_path' => __DIR__.'/.cache',
       
       // Optional. Will be used only if you choose Redis cache as cache_system
       'redis_dsn' => 'redis://localhost:6379',
       
       // Optional. Will be used only if you choose Memcached as cache_system
       'memcached_dsn' => 'memcached://localhost:11211',
       
       // Optional. If you use a CDN, a reverse proxy or a load balancer, set an array of IPs.
       // For other IPs, the bouncer will not trust the X-Forwarded-For header.
       // Default to empty array
       'trust_ip_forward_array' => [],

       // Optional. true to enable stream mode, true to enable the stream mode. Default to false.
       'stream_mode'=> false,
       
       // Optional. true to enable verbose debug log. Default to false
       'debug_mode' => false,
       
       // Optional. true to stop the process and display errors if any. Default to false.
       'display_errors' => false,
       
       // Optional. true to hide CrowdSec mentions on ban and captcha walls. Default to false.
       'hide_mentions' => false,
       
       // Optional. Only for test or debug purpose.
       // If not empty, it will be used for all remediation and geolocation processes.
       // Default to empty
       'forced_test_ip' => '1.2.3.4',
       
       // Optional. Cap the remediation to the selected one.
       // Select from 'bypass' (minimum remediation),'captcha' or 'ban' (maximum remediation).
       // Default to 'ban'.
       'max_remediation_level'=> 'ban',

       // Optional. Handle unknown remediations as.
       // Select from 'bypass' (minimum remediation), 'captcha' or 'ban' (maximum remediation).
       // Default to 'captcha'.
       'fallback_remediation'=> 'captcha',

       // Optional. Set the duration we keep in cache the fact that an IP is clean. In seconds. Defaults to 5.
       'cache_expiration_for_clean_ip'=> '5',

       // Optional. Set the duration we keep in cache the fact that an IP is bad. In seconds. Defaults to 20.
       'cache_expiration_for_bad_ip'=> '20',
       
       // Optional. Settings for geolocation remediation (i.e. country based remediation).
       'geolocation' => [
             // Optional. true to enable remediation based on country.
             // Default to false.
             'enabled' => false,
             // Optional. Geolocation system. Only 'maxmind' is available for the moment. 
             // Default to 'maxmind'
             'type' => 'maxmind',
             // Optional. true to store the geolocalized country in session
             // Setting true will avoid multiple call to the geolocalized system (e.g. maxmind database)
             // Default to true.
             'save_in_session' => true,
             // Optional. MaxMind settings
             'maxmind' => [
                       // Optional. Select from 'country' or 'city'.
                       // These are the two available MaxMind database types.
                       // Default to 'country'
                       'database_type' => 'country',
                       // Optional. Absolute path to the MaxMind database (mmdb file).
                       'database_path' => '/some/path/GeoLite2-Country.mmdb',
             ]
        ]       
   ]
   $bouncer = new Bouncer();
   $bouncer->configure($config);
```
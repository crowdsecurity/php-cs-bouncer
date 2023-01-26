<?php

// @see ../../docs/USER_GUIDE.md for possible settings details
use CrowdSecBouncer\Constants;

$crowdSecStandaloneBouncerConfig = [
    // ============================================================================#
    // Bouncer configs
    // ============================================================================#

    /** Select from 'bouncing_disabled', 'normal_bouncing' or 'flex_bouncing'.
     *
     * Choose if you want to apply CrowdSec directives (Normal bouncing) or be more permissive (Flex bouncing).
     * With the `Flex mode`, it is impossible to accidentally block access to your site to people who don’t
     * deserve it. This mode makes it possible to never ban an IP but only to offer a Captcha, in the worst-case
     * scenario.
     */
    'bouncing_level' => Constants::BOUNCING_LEVEL_NORMAL,

    /** If you use a CDN, a reverse proxy or a load balancer, you can use this setting to whitelist their IPs.
     *
     * For other IPs, the bouncer will not trust the X-Forwarded-For header.
     *
     * With the Standalone bouncer, you have to set an array of Ips : ['1.2.3.4', '5.6.7.8'] for example
     * The standalone bouncer will automatically transform this array to an array of comparable IPs arrays:
     * [['001.002.003.004', '001.002.003.004'], ['005.006.007.008', '005.006.006.007']]
     *
     * If you use your own bouncer, you should have to set directly an array of comparable IPs arrays
     */
    'trust_ip_forward_array' => [],
    /**
     * By default, the lib call the REST LAPI using file_get_contents method (allow_url_fopen is required).
     * Set 'use_curl' to true in order to use cURL request instead (curl is in then required).
     */
    'use_curl' => false,

    /**
     * array of URIs that will not be bounced.
     */
    'excluded_uris' => ['/favicon.ico'],

    // Select from 'phpfs' (File system cache), 'redis' or 'memcached'.
    'cache_system' => Constants::CACHE_SYSTEM_PHPFS,

    // Set the duration we keep in cache the captcha flow variables for an IP. In seconds. Defaults to 86400.
    'captcha_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CAPTCHA,

    // true to enable verbose debug log.
    'debug_mode' => false,
    // true to disable prod log
    'disable_prod_log' => false,

    /** Absolute path to store log files.
     *
     * Important note: be sur this path won't be publicly accessible
     */
    'log_directory_path' => __DIR__ . '/.logs',

    // true to stop the process and display errors if any.
    'display_errors' => false,

    /** Only for test or debug purpose. Default to empty.
     *
     * If not empty, it will be used instead of the real remote ip.
     */
    'forced_test_ip' => '',

    /** Only for test or debug purpose. Default to empty.
     *
     * If not empty, it will be used instead of the real forwarded ip.
     * If set to "no_forward", the x-forwarded-for mechanism will not be used at all.
     */
    'forced_test_forwarded_ip' => '',

    // Settings for ban and captcha walls
    'custom_css' => '',
    // true to hide CrowdSec mentions on ban and captcha walls.
    'hide_mentions' => false,
    'color' => [
        'text' => [
            'primary' => 'black',
            'secondary' => '#AAA',
            'button' => 'white',
            'error_message' => '#b90000',
        ],
        'background' => [
            'page' => '#eee',
            'container' => 'white',
            'button' => '#626365',
            'button_hover' => '#333',
        ],
    ],
    'text' => [
        // Settings for captcha wall
        'captcha_wall' => [
            'tab_title' => 'Oops..',
            'title' => 'Hmm, sorry but...',
            'subtitle' => 'Please complete the security check.',
            'refresh_image_link' => 'refresh image',
            'captcha_placeholder' => 'Type here...',
            'send_button' => 'CONTINUE',
            'error_message' => 'Please try again.',
            'footer' => '',
        ],
        // Settings for ban wall
        'ban_wall' => [
            'tab_title' => 'Oops..',
            'title' => '🤭 Oh!',
            'subtitle' => 'This page is protected against cyber attacks and your IP has been banned by our system.',
            'footer' => '',
        ],
    ],

    // ============================================================================#
    // Client configs
    // ============================================================================#

    /** Select from 'api_key' and 'tls'.
     *
     * Choose if you want to use an API-KEY or a TLS (pki) authentification
     * TLS authentication is only available if you use CrowdSec agent with a version superior to 1.4.0
     */
    'auth_type' => Constants::AUTH_KEY,

    /** Absolute path to the bouncer certificate.
     *
     * Only required if you choose tls as "auth_type"
     */
    'tls_cert_path' => '',

    /** Absolute path to the bouncer key.
     *
     * Only required if you choose tls as "auth_type"
     */
    'tls_key_path' => '',

    /** This option determines whether request handler verifies the authenticity of the peer's certificate.
     *
     * When negotiating a TLS or SSL connection, the server sends a certificate indicating its identity.
     * If "tls_verify_peer" is set to true, request handler verifies whether the certificate is authentic.
     * This trust is based on a chain of digital signatures,
     * rooted in certification authority (CA) certificates you supply using the "tls_ca_cert_path" setting below.
     */
    'tls_verify_peer' => true,

    /** Absolute path to the CA used to process peer verification.
     *
     * Only required if you choose tls as "auth_type" and "tls_verify_peer" is true
     */
    'tls_ca_cert_path' => '',

    /** The bouncer api key to access LAPI.
     *
     * Key generated by the cscli (CrowdSec cli) command like "cscli bouncers add bouncer-php-library"
     * Only required if you choose api_key as "auth_type"
     */
    'api_key' => 'YOUR_BOUNCER_API_KEY',

    /** Define the URL to your LAPI server, default to http://localhost:8080.
     *
     * If you have installed the CrowdSec agent on your server, it should be "http://localhost:8080" or
     * "https://localhost:8080"
     */
    'api_url' => Constants::DEFAULT_LAPI_URL,

    // In seconds. The timeout when calling LAPI. Must be greater or equal than 1. Defaults to 1 sec.
    'api_timeout' => 1,

    // ============================================================================#
    // Remediation engine configs
    // ============================================================================#

    /** Select from 'bypass' (minimum remediation), 'captcha' or 'ban' (maximum remediation).
     * Default to 'captcha'.
     *
     * Handle unknown remediations as.
     */
    'fallback_remediation' => Constants::REMEDIATION_CAPTCHA,

    /**
     * The `ordered_remediations` setting accepts an array of remediations ordered by priority.
     * If there are more than one decision for an IP, remediation with the highest priority will be return.
     * The specific remediation `bypass` will always be considered as the lowest priority (there is no need to
     * specify it in this setting).
     * This setting is not required. If you don't set any value, `['ban']` will be used by default for CAPI remediation
     * and `['ban', 'captcha']` for LAPI remediation.
     */
    'ordered_remediations' => [Constants::REMEDIATION_BAN, Constants::REMEDIATION_CAPTCHA],

    /** Will be used only if you choose File system as cache_system.
     *
     * Important note: be sur this path won't be publicly accessible
     */
    'fs_cache_path' => __DIR__ . '/.cache',

    // Will be used only if you choose Redis cache as cache_system
    'redis_dsn' => 'redis://localhost:6379',

    // Will be used only if you choose Memcached as cache_system
    'memcached_dsn' => 'memcached://localhost:11211',

    // Set the duration we keep in cache the fact that an IP is clean. In seconds. Defaults to 5.
    'clean_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,

    // Set the duration we keep in cache the fact that an IP is bad. In seconds. Defaults to 20.
    'bad_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_BAD_IP,

    /** true to enable stream mode, false to enable the live mode. Default to false.
     *
     * By default, the `live mode` is enabled. The first time a stranger connects to your website, this mode
     * means that the IP will be checked directly by the CrowdSec API. The rest of your user’s browsing will be
     * even more transparent thanks to the fully customizable cache system.
     *
     * But you can also activate the `stream mode`. This mode allows you to constantly feed the bouncer with the
     * malicious IP list via a background task (CRON), making it to be even faster when checking the IP of your
     * visitors. Besides, if your site has a lot of unique visitors at the same time, this will not influence the
     * traffic to the API of your CrowdSec instance.
     */
    'stream_mode' => false,

    // Settings for geolocation remediation (i.e. country based remediation).
    'geolocation' => [
        // true to enable remediation based on country. Default to false.
        'enabled' => false,
        // Geolocation system. Only 'maxmind' is available for the moment. Default to 'maxmind'
        'type' => Constants::GEOLOCATION_TYPE_MAXMIND,
        /**
         * This setting will be used to set the lifetime (in seconds) of a cached country
         * associated to an IP. The purpose is to avoid multiple call to the geolocation system (e.g. maxmind database)
         * . Default to 86400. Set 0 to disable caching.
         */
        'cache_duration' => Constants::CACHE_EXPIRATION_FOR_GEO,
        // MaxMind settings
        'maxmind' => [
            /**Select from 'country' or 'city'. Default to 'country'
             *
             * These are the two available MaxMind database types.
             */
            'database_type' => Constants::MAXMIND_COUNTRY,
            // Absolute path to the MaxMind database (mmdb file).
            'database_path' => '/some/path/GeoLite2-Country.mmdb',
        ],
    ],
];

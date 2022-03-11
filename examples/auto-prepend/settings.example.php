<?php
// @see ../../docs/USER_GUIDE.md for details
use CrowdSecBouncer\Constants;

$crowdSecStandaloneBouncerConfig = [
    'api_url' => 'http://url-to-your-lapi:8080',
    'api_key' => '...',
    'debug_mode' => false,
    'display_errors' => false,
    'log_directory_path' => __DIR__.'/.logs',
    'fs_cache_path' => __DIR__.'/.cache',

    'bouncing_level' => Constants::BOUNCING_LEVEL_NORMAL,
    'forced_test_ip' => '',

    'stream_mode' => false,

    'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
    'redis_dsn' => '',
    'memcached_dsn' => '',

    'clean_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,
    'bad_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_BAD_IP,
    'fallback_remediation' => Constants::REMEDIATION_CAPTCHA,

    'hide_mentions' => false,
    'trust_ip_forward_array' => [],

    'theme_color_text_primary' => 'black',
    'theme_color_text_secondary' => '#AAA',
    'theme_color_text_button' => 'white',
    'theme_color_text_error_message' => '#b90000',
    'theme_color_background_page' => '#eee',
    'theme_color_background_container' => 'white',
    'theme_color_background_button' => '#626365',
    'theme_color_background_button_hover' => '#333',

    'theme_text_captcha_wall_tab_title' => 'Oops..',
    'theme_text_captcha_wall_title' => 'Hmm, sorry but...',
    'theme_text_captcha_wall_subtitle' => 'Please complete the security check.',
    'theme_text_captcha_wall_refresh_image_link' => 'refresh image',
    'theme_text_captcha_wall_captcha_placeholder' => 'Type here...',
    'theme_text_captcha_wall_send_button' => 'CONTINUE',
    'theme_text_captcha_wall_error_message' => 'Please try again.',
    'theme_text_captcha_wall_footer' => '',

    'theme_text_ban_wall_tab_title' => 'Oops..',
    'theme_text_ban_wall_title' => '🤭 Oh!',
    'theme_text_ban_wall_subtitle' => 'This page is protected against cyber attacks and your IP has been banned by our system.',
    'theme_text_ban_wall_footer' => '',
    'theme_custom_css' => '',

    'geolocation' => [
        'save_in_session' => true,
        'enabled' => true,
        'type' => 'maxmind',
        'maxmind' => [
            'database_type' => 'country',
            'database_path' => '/var/www/html/GeoLite2-Country.mmdb'
        ]
    ]
];

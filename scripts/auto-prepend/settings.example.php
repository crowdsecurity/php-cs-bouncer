<?php
// @see ../../docs/USER_GUIDE.md for possible settings details
use CrowdSecBouncer\Constants;

$crowdSecStandaloneBouncerConfig = [
     // LAPI Connection
    'api_url' => 'http://localhost:8080',
    'api_key' => '...',
    'api_user_agent' => Constants::BASE_USER_AGENT,
     // Debug
    'debug_mode' => false,
    'display_errors' => false,
    'log_directory_path' => __DIR__.'/.logs',
    'forced_test_ip' => '',
    // Bouncer settings
    'bouncing_level' => Constants::BOUNCING_LEVEL_NORMAL,
    'fallback_remediation' => Constants::REMEDIATION_CAPTCHA,
    'trust_ip_forward_array' => [],
    // Cache settings
    'stream_mode' => false,
    'fs_cache_path' => __DIR__.'/.cache',
    'cache_system' => Constants::CACHE_SYSTEM_PHPFS,
    'redis_dsn' => '',
    'memcached_dsn' => '',
    'clean_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,
    'bad_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_BAD_IP,
    // Walls common settings
    'hide_mentions' => false,
    'theme_color_text_primary' => 'black',
    'theme_color_text_secondary' => '#AAA',
    'theme_color_text_button' => 'white',
    'theme_color_text_error_message' => '#b90000',
    'theme_color_background_page' => '#eee',
    'theme_color_background_container' => 'white',
    'theme_color_background_button' => '#626365',
    'theme_color_background_button_hover' => '#333',
    'theme_custom_css' => '',
    // Captcha wall settings
    'theme_text_captcha_wall_tab_title' => 'Oops..',
    'theme_text_captcha_wall_title' => 'Hmm, sorry but...',
    'theme_text_captcha_wall_subtitle' => 'Please complete the security check.',
    'theme_text_captcha_wall_refresh_image_link' => 'refresh image',
    'theme_text_captcha_wall_captcha_placeholder' => 'Type here...',
    'theme_text_captcha_wall_send_button' => 'CONTINUE',
    'theme_text_captcha_wall_error_message' => 'Please try again.',
    'theme_text_captcha_wall_footer' => '',
    // Ban wall settings
    'theme_text_ban_wall_tab_title' => 'Oops..',
    'theme_text_ban_wall_title' => '🤭 Oh!',
    'theme_text_ban_wall_subtitle' => 'This page is protected against cyber attacks and your IP has been banned by our system.',
    'theme_text_ban_wall_footer' => '',
    // Geolocation
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

<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use CrowdSec\RemediationEngine\Constants as RemConstants;

/**
 * Every constant of the library are set here.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Constants extends RemConstants
{
    /** @var string The URL prefix for Blocklist As A Service LAPI */
    public const BAAS_URL = 'https://admin.api.crowdsec.net';
    /** @var int The duration we keep a captcha flow in cache */
    public const CACHE_EXPIRATION_FOR_CAPTCHA = 86400;
    /** @var string The "MEMCACHED" cache system */
    public const CACHE_SYSTEM_MEMCACHED = 'memcached';
    /** @var string The "PHPFS" cache system */
    public const CACHE_SYSTEM_PHPFS = 'phpfs';
    /** @var string The "REDIS" cache system */
    public const CACHE_SYSTEM_REDIS = 'redis';
    /** @var string Cache tag for captcha flow */
    public const CACHE_TAG_CAPTCHA = 'captcha';
    /** @var string Path for html templates folder (e.g. ban and captcha wall) */
    public const TEMPLATES_DIR = __DIR__ . '/templates';
    /** @var string The last version of this library */
    public const VERSION = 'v4.3.0';
    /** @var string The "disabled" x-forwarded-for setting */
    public const X_FORWARDED_DISABLED = 'no_forward';
}

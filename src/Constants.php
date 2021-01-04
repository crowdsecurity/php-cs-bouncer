<?php

namespace CrowdSecBouncer;

/**
 * Every constants of the library are set here.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Constants
{
    /** @var string The URL of the CrowdSec Central API */
    public const CAPI_URL = 'https://api.crowdsec.net/v2/';

    /** @var string The last version of this library */
    public const VERSION = 'v0.8.5';

    /** @var string The user agent used to send request to LAPI or CAPI */
    public const BASE_USER_AGENT = 'PHP CrowdSec Bouncer/'.self::VERSION;

    /** @var int The timeout when calling LAPI or CAPI */
    public const API_TIMEOUT = 1;

    /** @var int The duration we keep a clean IP in cache 5s */
    public const CACHE_EXPIRATION_FOR_CLEAN_IP = 5;

    /** @var int The duration we keep a bad IP in cache 20s */
    public const CACHE_EXPIRATION_FOR_BAD_IP = 20;

    /** @var string The ban remediation */
    public const REMEDIATION_BAN = 'ban';

    /** @var string The captcha remediation */
    public const REMEDIATION_CAPTCHA = 'captcha';

    /** @var string The bypass remediation */
    public const REMEDIATION_BYPASS = 'bypass';

    /** @var array<string> The list of each known remediation, sorted by priority */
    public const ORDERED_REMEDIATIONS = [self::REMEDIATION_BAN, self::REMEDIATION_CAPTCHA, self::REMEDIATION_BYPASS];
}

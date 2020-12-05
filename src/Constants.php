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
    const CAPI_URL = 'https://api.crowdsec.net/v2/'; // TODO P2 get the correct one

    /** @var string The user agent used to send request to LAPI or CAPI */
    const BASE_USER_AGENT = 'CrowdSec PHP Library/1.0.0'; // TODO P3 get the correct version

    /** @var int The timeout when calling LAPI or CAPI */
    const API_TIMEOUT = 1; // TODO P2 get the correct one

    /** @var int The duration we keep a clean IP in cache 600s = 10m */
    const CACHE_EXPIRATION_FOR_CLEAN_IP = 600; // TODO P2 get the correct one

    /** @var array The list of each known remediation, sorted by priority */
    const ORDERED_REMEDIATIONS = ['ban', 'captcha', 'clean']; // TODO P2 get the correct one
}

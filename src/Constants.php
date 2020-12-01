<?php

namespace CrowdSecBouncer;

/**
 * Every constants of the library are set here.
 * 
 * @author    CrowdSec team
 * @link      https://crowdsec.net CrowdSec Official Website
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Constants
{
    /** @var string The URL of the CrowdSec Central API */
    const CAPI_URL = 'https://api.crowdsec.net/v2/';// TODO P2 get the correct one

    /** @var string The user agent used to send request to LAPI or CAPI */
    const BASE_USER_AGENT = 'CrowdSec PHP Library/1.0.0';// P3 TODO get the correct version

    /** @var int The timeout when calling LAPI or CAPI */
    const API_TIMEOUT = 1;// TODO P2 get the correct one

    /** @var array The list of each known remediation, sorted by priority */
    const ORDERED_REMEDIATIONS = ['ban', 'captcha'];// P2 TODO get the correct one
}

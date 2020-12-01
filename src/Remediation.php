<?php

namespace CrowdSecBouncer;

/**
 * Remediation Helpers.
 * 
 * @author    CrowdSec team
 * @link      https://crowdsec.net CrowdSec Official Website
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Remediation
{
    /**
     * Compare two priorities.
     */
    private static function comparePriorities($a, $b)
    {
        $a = $a[3];
        $b = $b[3];
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? -1 : 1;
    }

    /**
     * Add numerical priority allowing easy sorting.
     */
    private static function addPriority(array $remediation)
    {
        $prio = array_search($remediation[0], Constants::ORDERED_REMEDIATIONS);

        // Considere every unknown type as a top priority
        $remediation[3] = $prio !== false ? $prio : 0;
        return $remediation;
    }

    /**
     * Sort the remediations array of a cache item, by remediation priorities.
     */
    public static function sortRemediationByPriority(array $remediations)
    {
        // Add priority.
        $remediations = array_map('self::addPriority', $remediations);

        // Sort by priority.
        usort($remediations, 'self::comparePriorities');

        return $remediations;
    }

    /**
     * Parse "duration" entries returned from API to a number of seconds.
     * 
     * TODO TEST P3
     *   $str = '9999h59m56.603445s
     *   10m33.3465483s
     *   33.3465483s';
     *   33s'// should break!;
     */
    private static function parseDurationToSeconds(string $duration): int
    {
        $re = '/(?:(?:(\d+)h)?(\d+)m)?(\d+).\d+s/m';
        preg_match($re, $duration, $matches);
        $seconds = 0;
        if ($matches[1] !== null) {
            $seconds += ((int)$matches[1]) * 3600;
        }
        if ($matches[2] !== null) {
            $seconds += ((int)$matches[2]) * 60;
        }
        if ($matches[3] !== null) {
            $seconds += ((int)$matches[1]);
        }
        return $seconds;
    }

    /**
     * Format a remediation item of a cache item.
     * This format use a minimal amount of data allowing less cache data consumption.
     * 
     * TODO TESTS P3
     */
    public static function formatFromDecision(?array $decision)
    {
        if (!$decision) {
            return ['clear', 0, null];
        }
        return [
            $decision['type'], // ex: captcha
            time() + self::parseDurationToSeconds($decision['duration']), // expiration
            $decision['id']

            /*
            TODO P3 useful to keep in cache?
            [
                $decision['id'],// id from API
                $decision['origin'],// ex cscli
                $decision['scenario'],//ex: "manual 'captcha' from '25b9f1216f9344b780963bd281ae5573UIxCiwc74i2mFqK4'"
                $decision['scope'],// ex: IP
            ]
            */
        ];
    }
}

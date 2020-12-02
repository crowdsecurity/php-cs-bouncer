<?php

namespace CrowdSecBouncer;

/**
 * Remediation Helpers.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Remediation
{
    /**
     * Compare two priorities.
     */
    private static function comparePriorities(array $a, array $b): int
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
    private static function addPriority(array $remediation): array
    {
        $prio = array_search($remediation[0], Constants::ORDERED_REMEDIATIONS);

        // Considere every unknown type as a top priority
        $remediation[3] = false !== $prio ? $prio : 0;

        return $remediation;
    }

    /**
     * Sort the remediations array of a cache item, by remediation priorities.
     */
    public static function sortRemediationByPriority(array $remediations): array
    {
        // Add priorities.
        $remediationsWithPriorities = [];
        foreach ($remediations as $key => $remediation) {
            $remediationsWithPriorities[$key] = self::addPriority($remediation);
        }

        // Sort by priorities.
        /** @var callable */
        $compareFunction = 'self::comparePriorities';
        usort($remediationsWithPriorities, $compareFunction);

        return $remediationsWithPriorities;
    }

    /**
     * Parse "duration" entries returned from API to a number of seconds.
     *
     * TODO TEST P3
     *   9999h59m56.603445s
     *   10m33.3465483s
     *   33.3465483s
     *   -285.876962ms
     *   33s'// should break!;
     */
    private static function parseDurationToSeconds(string $duration): int
    {
        $re = '/(-?)(?:(?:(\d+)h)?(\d+)m)?(\d+).\d+(m?)s/m';
        preg_match($re, $duration, $matches);
        if (!count($matches)) {
            throw new BouncerException("Unable to parse the following duration: ${$duration}.");
        };
        $seconds = 0;
        if (null !== $matches[2]) {
            $seconds += ((int) $matches[1]) * 3600;// hours
        }
        if (null !== $matches[3]) {
            $seconds += ((int) $matches[2]) * 60;// minutes
        }
        if (null !== $matches[4]) {
            $seconds += ((int) $matches[1]);// seconds
        }
        if (null !== $matches[5]) {// units in milliseconds
            $seconds *= 0.001;
        }
        if (null !== $matches[1]) {// negative
            $seconds *= -1;
        }
        $seconds = round($seconds);

        return $seconds;
    }

    /**
     * Format a remediation item of a cache item.
     * This format use a minimal amount of data allowing less cache data consumption.
     *
     * TODO TESTS P3
     */
    public static function formatFromDecision(?array $decision): array
    {
        if (!$decision) {
            return ['clear', 0, null];
        }

        return [
            $decision['type'], // ex: captcha
            time() + self::parseDurationToSeconds($decision['duration']), // expiration
            $decision['id'],

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

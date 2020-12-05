<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Psr\Log\LoggerInterface;

/**
 * The cache mecanism to store every decisions from LAPI/CAPI. Symfony Cache component powered.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class ApiCache
{
    /** @var LoggerInterface */
    private $logger;

    /** @var AbstractAdapter */
    private $adapter;

    /** @var bool */
    private $ruptureMode;

    /** @var int */
    private $cacheExpirationForCleanIp;

    /** @var ApiClient */
    private $apiClient;

    /** @var bool */
    private $warmedUp = false;

    public function __construct(ApiClient $apiClient = null, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->apiClient = $apiClient ?: new ApiClient($logger);
    }

    /**
     * Configure this instance.
     */
    public function configure(AbstractAdapter $adapter, bool $ruptureMode, string $apiUrl, int $timeout, string $userAgent, string $token, int $cacheExpirationForCleanIp): void
    {
        $this->adapter = $adapter;
        $this->ruptureMode = $ruptureMode;
        $this->cacheExpirationForCleanIp = $cacheExpirationForCleanIp;
        $this->logger->debug('Api Cache adapter: '.get_class($adapter));
        $this->logger->debug('Api Cache mode: '.($ruptureMode ? 'rupture' : 'stream'));
        $this->logger->debug("Api Cache expiration for clean ips: $cacheExpirationForCleanIp sec");

        $this->apiClient->configure($apiUrl, $timeout, $userAgent, $token);
    }

    /**
     * Build a Symfony Cache Item from a couple of IP and its computed remediation.
     */
    private function buildRemediationCacheItem(int $ip, string $type, int $expiration, int $decisionId): CacheItem
    {
        $item = $this->adapter->getItem((string) $ip);

        // Merge with existing remediations (if any).
        $remediations = $item->get();
        $remediations = $remediations ?: [];
        $remediations[$decisionId] = [
            $type,
            $expiration,
            $decisionId,
        ]; // erase previous decision with the same id

        // Build the item lifetime in cache and sort remediations by priority
        $maxLifetime = max(array_column($remediations, 1));
        $prioritizedRemediations = Remediation::sortRemediationByPriority($remediations);

        $item->set($prioritizedRemediations);
        $item->expiresAfter($maxLifetime);

        return $item;
    }

    /**
     * Save the cache without committing it to the cache system. Useful to improve performance when updating the cache.
     */
    private function saveDeferred(CacheItem $item, int $ip, string $type, int $expiration, int $decisionId): void
    {
        $isQueued = $this->adapter->saveDeferred($item);
        if (!$isQueued) {
            $ipStr = long2ip($ip);
            throw new BouncerException("Unable to save this deferred item in cache: ${$ipStr} =>$type (for $expiration sec, #$decisionId)");
        }
    }

    /**
     * Parse "duration" entries returned from API to a number of seconds.
     *
     * TODO P3 TEST
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
            $seconds += ((int) $matches[1]) * 3600; // hours
        }
        if (null !== $matches[3]) {
            $seconds += ((int) $matches[2]) * 60; // minutes
        }
        if (null !== $matches[4]) {
            $seconds += ((int) $matches[1]); // seconds
        }
        if (null !== $matches[5]) { // units in milliseconds
            $seconds *= 0.001;
        }
        if (null !== $matches[1]) { // negative
            $seconds *= -1;
        }
        $seconds = round($seconds);

        return (int)$seconds;
    }



    /**
     * Format a remediation item of a cache item.
     * This format use a minimal amount of data allowing less cache data consumption.
     *
     * TODO P3 TESTS
     */
    private function formatRemediationFromDecision(?array $decision): array
    {
        if (!$decision) {
            return ['clean', time() + $this->cacheExpirationForCleanIp, 0];
        }

        return [
            $decision['type'], // ex: captcha
            time() + self::parseDurationToSeconds($decision['duration']), // expiration timestamp
            $decision['id'],
        ];
    }

    /**
     * Update the cached remediations from these new decisions.

     * TODO P2 WRITE TESTS
     * 0 decisions
     * 3 known remediation type
     * 3 decisions but 1 unknown remediation type
     * 3 unknown remediation type
     */
    private function saveRemediations(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            $ipRange = range($decision['start_ip'], $decision['end_ip']);
            foreach ($ipRange as $ip) {
                $remediation = $this->formatRemediationFromDecision($decision);
                $item = $this->buildRemediationCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
                $this->saveDeferred($item, $ip, $remediation[0], $remediation[1], $remediation[2]);
            }
        }

        return $this->adapter->commit();
    }

    /**
     * Update the cached remediation of the specified IP from these new decisions.
     */
    private function saveRemediationsForIp(array $decisions, int $ip): void
    {
        if (\count($decisions)) {
            foreach ($decisions as $decision) {
                $remediation = $this->formatRemediationFromDecision($decision);
                $item = $this->buildRemediationCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
                $this->saveDeferred($item, $ip, $remediation[0], $remediation[1], $remediation[2]);
            }
        } else {
            $remediation = $this->formatRemediationFromDecision(null);
            $item = $this->buildRemediationCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
            $this->saveDeferred($item, $ip, $remediation[0], $remediation[1], $remediation[2]);
        }
        $this->adapter->commit();
    }

    /**
     * Used in stream mode only.
     * Warm the cache up.
     * Used when the stream mode has just been activated.
     *
     * TODO P2 test for overlapping decisions strategy (max expires, remediation ordered by priorities)
     */
    public function warmUp(): void
    {
        $this->logger->info('Warming the cache up');
        $startup = true;
        $decisionsDiff = $this->apiClient->getStreamedDecisions($startup);
        $newDecisions = $decisionsDiff['new'];

        $this->adapter->clear();

        if ($newDecisions) {
            $this->warmedUp = $this->saveRemediations($newDecisions);
            if (!$this->warmedUp) {
                throw new BouncerException("Unable to warm the cache up");
            }
        }
        $this->logger->debug('Cache warmed up');
    }

    /**
     * Used in stream mode only.
     * Pull decisions updates from the API and update the cached remediations.
     * Used for the stream mode when we have to update the remediations list.
     */
    public function pullUpdates(): void
    {
        // TODO P1 Finish stream mode with pull update + dont forget to delete old decisions!
    }

    /**
     * Used in rupture mode only.
     * This method is called when nothing has been found in cache for the requested IP.
     * This call the API for decisions concerning the specified IP. Finally the result is stored.
     * Whether decisions has been found or not.
     */
    private function miss(int $ip): string
    {
        $decisions = $this->apiClient->getFilteredDecisions(['ip' => long2ip($ip)]);
        $this->saveRemediationsForIp($decisions, $ip);
        return $this->hit($ip);
    }

    /**
     * Used in both mode (stream and ruptue).
     * This method formats the cached item as a remediation.
     * It returns the highest remediation level found.
     */
    private function hit(int $ip): string
    {
        $remediations = $this->adapter->getItem((string) $ip)->get();
        // TODO P2 control before if date is not expired and if true, update cache item.

        // We apply array values first because keys are ids.
        $firstRemediation = array_values($remediations)[0];
        /** @var string */
        $firstRemediationString = $firstRemediation[0];

        return $firstRemediationString;
    }

    /**
     * Request the cache for the specified IP.
     *
     * @return string the computed remediation string, or null if no decision was found
     */
    public function get(int $ip): ?string
    {
        $this->logger->debug('IP to check: '.$ip);
        if (!$this->ruptureMode && !$this->warmedUp) {
            throw new BouncerException('CrowdSec Bouncer configured in "stream" mode. Please warm the cache up before trying to access it.');
        }

        if ($this->adapter->hasItem((string) $ip)) {
            return $this->hit($ip);
        } elseif ($this->ruptureMode) {
            return $this->miss($ip);
        }

        return $this->formatRemediationFromDecision(null)[0];
    }
}

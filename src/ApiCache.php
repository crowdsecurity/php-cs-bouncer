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
    private $liveMode;

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
    public function configure(AbstractAdapter $adapter, bool $liveMode, string $apiUrl, int $timeout, string $userAgent, string $token, int $cacheExpirationForCleanIp): void
    {
        $this->adapter = $adapter;
        $this->liveMode = $liveMode;
        $this->cacheExpirationForCleanIp = $cacheExpirationForCleanIp;
        $this->logger->debug('Api Cache adapter: '.get_class($adapter));
        $this->logger->debug('Api Cache mode: '.($liveMode ? 'live' : 'stream'));
        $this->logger->debug("Api Cache expiration for clean ips: $cacheExpirationForCleanIp sec");

        $this->apiClient->configure($apiUrl, $timeout, $userAgent, $token);
    }

    /**
     * Add remediation to a Symfony Cache Item identified by IP
     */
    private function addRemediationToCacheItem(string $ip, string $type, int $expiration, int $decisionId): void
    {
        $item = $this->adapter->getItem($ip);

        // Merge with existing remediations (if any).
        $remediations = $item->get();
        $remediations = $remediations ?: [];

        $index = array_search(Constants::REMEDIATION_BYPASS, array_column($remediations, 0));
        if (false !== $index) {
            $this->logger->debug("cache#$ip: Previously clean IP but now bad, remove the ".Constants::REMEDIATION_BYPASS." remediation immediately");
            unset($remediations[$index]);   
        }

        $remediations[] = [
            $type,
            $expiration,
            $decisionId,
        ]; // erase previous decision with the same id

        // Build the item lifetime in cache and sort remediations by priority
        $maxLifetime = max(array_column($remediations, 1));
        $prioritizedRemediations = Remediation::sortRemediationByPriority($remediations);

        //$this->logger->debug("Decision $decisionId added to cache item $ip with lifetime $maxLifetime. Now it looks like:");
        //dump($prioritizedRemediations);
        $item->set($prioritizedRemediations);
        $item->expiresAfter($maxLifetime);

        // Save the cache without committing it to the cache system.
        // Useful to improve performance when updating the cache.
        if (!$this->adapter->saveDeferred($item)) {
            throw new BouncerException("cache#$ip: Unable to save this deferred item in cache: $type for $expiration sec, (decision $decisionId)");
        }
    }

    /**
     * Remove a decision from a Symfony Cache Item identified by ip
     */
    private function removeDecisionFromRemediationItem(string $ip, int $decisionId): void
    {
        //$this->logger->debug("Remove decision $decisionId from the cache item matching ip ".$ip);
        $item = $this->adapter->getItem($ip);
        $remediations = $item->get();
        //dump($remediations);

        $index = false;
        if ($remediations) {
            $index = array_search($decisionId, array_column($remediations, 2));
        }
        
        if (false === $index) {
            // TODO P3 this seems to be a bug from LAPI;-. Investigate.
            $this->logger->info("cache#$ip: decision $decisionId not found in cache.");
            return;
        }
        unset($remediations[$index]);

        if (!$remediations) {
            $this->logger->debug("cache#$ip: No more remediation for cache. Let's remove the cache item");
            $this->adapter->delete($ip);
            return;
        }
        // Build the item lifetime in cache and sort remediations by priority
        $maxLifetime = max(array_column($remediations, 1));
        $cacheContent = Remediation::sortRemediationByPriority($remediations);
        $item->expiresAfter($maxLifetime);
        $item->set($cacheContent);

        // Save the cache without commiting it to the cache system.
        // Useful to improve performance when updating the cache.
        if (!$this->adapter->saveDeferred($item)) {
            throw new BouncerException("cache#$ip: Unable to save item");
        }
        $this->logger->debug("cache#$ip: Decision $decisionId successfuly removed -deferred-");
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
            return [Constants::REMEDIATION_BYPASS, time() + $this->cacheExpirationForCleanIp, 0];
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
            $ipRange = array_map('long2ip', range($decision['start_ip'], $decision['end_ip']));
            $remediation = $this->formatRemediationFromDecision($decision);
            foreach ($ipRange as $ip) {
                $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
            }
        }

        return $this->adapter->commit();
    }

    private function removeRemediations(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            $ipRange = array_map('long2ip', range($decision['start_ip'], $decision['end_ip']));
            $this->logger->debug('decision#'.$decision['id'].': remove for IPs '.join(', ', $ipRange));
            $remediation = $this->formatRemediationFromDecision($decision);
            foreach ($ipRange as $ip) {
                $this->removeDecisionFromRemediationItem($ip, $remediation[2]);
            }
        }
        return $this->adapter->commit();
    }

    /**
     * Update the cached remediation of the specified IP from these new decisions.
     */
    private function saveRemediationsForIp(array $decisions, string $ip): void
    {
        if (\count($decisions)) {
            foreach ($decisions as $decision) {
                $remediation = $this->formatRemediationFromDecision($decision);
                $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
            }
        } else {
            $remediation = $this->formatRemediationFromDecision(null);
            $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
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
        //dump($decisionsDiff);
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
        $this->logger->info('Pulling updates from API');
        if (!$this->warmedUp) {
            throw new BouncerException("You have to warm the cache up before trying to pull updates.");
        }

        $decisionsDiff = $this->apiClient->getStreamedDecisions();
        //dump($decisionsDiff);
        $newDecisions = $decisionsDiff['new'];
        $deletedDecisions = $decisionsDiff['deleted'];

        if ($deletedDecisions) {
            $this->removeRemediations($deletedDecisions);
        }

        if ($newDecisions) {
            $this->saveRemediations($newDecisions);
        }
        $this->logger->debug('Updates pulled from API');
    }

    /**
     * This method is called when nothing has been found in cache for the requested IP.
     * In live mode is enabled, calls the API for decisions concerning the specified IP
     * In stream mode, as we considere cache is the single source of truth, the IP is considered clean.
     * Finally the result is stored in caches for further calls.
     */
    private function miss(string $ip): string
    {
        $decisions = [];

        if ($this->liveMode) {
            $this->logger->debug("Direct call to API for $ip");
            $decisions = $this->apiClient->getFilteredDecisions(['ip' => $ip]);
        }

        $this->saveRemediationsForIp($decisions, $ip);
        return $this->hit($ip);
    }

    /**
     * Used in both mode (stream and ruptue).
     * This method formats the cached item as a remediation.
     * It returns the highest remediation level found.
     */
    private function hit(string $ip): string
    {
        $remediations = $this->adapter->getItem($ip)->get();
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
    public function get(string $ip): string
    {
        $this->logger->debug('IP to check: '.$ip);
        if (!$this->liveMode && !$this->warmedUp) {
            throw new BouncerException('CrowdSec Bouncer configured in "stream" mode. Please warm the cache up before trying to access it.');
        }

        if ($this->adapter->hasItem($ip)) {
            $this->logger->debug("Cache hit for IP: $ip");
            return $this->hit($ip);
        } else {
            $this->logger->debug("Cache miss for IP: $ip");
            return $this->miss($ip);
        }

        return $this->formatRemediationFromDecision(null)[0];
    }
}

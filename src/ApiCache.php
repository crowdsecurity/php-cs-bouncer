<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\PruneableInterface;
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
    private $warmedUp;

    public function __construct(ApiClient $apiClient = null, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->apiClient = $apiClient ?: new ApiClient($logger);
    }

    /**
     * Configure this instance.
     */
    public function configure(
        AbstractAdapter $adapter,
        bool $liveMode,
        string $apiUrl,
        int $timeout,
        string $userAgent,
        string $apiKey,
        int $cacheExpirationForCleanIp
    ): void {
        $this->adapter = $adapter;
        $this->liveMode = $liveMode;
        $this->cacheExpirationForCleanIp = $cacheExpirationForCleanIp;
        $this->logger->debug('Api Cache adapter: ' . get_class($adapter));
        $this->logger->debug('Api Cache mode: ' . ($liveMode ? 'live' : 'stream'));
        $this->logger->debug("Api Cache expiration for clean ips: $cacheExpirationForCleanIp sec");
        $cacheConfigItem = $this->adapter->getItem('cacheConfig');
        $cacheConfig = $cacheConfigItem->get();
        $warmedUp = (is_array($cacheConfig) && isset($cacheConfig['warmed_up']) && $cacheConfig['warmed_up'] === true);
        $this->warmedUp = $warmedUp;
        $this->logger->debug("Api Cache already warmed up: " . ($this->warmedUp ? 'true' : 'false'));

        $this->apiClient->configure($apiUrl, $timeout, $userAgent, $apiKey);
    }

    /**
     * Add remediation to a Symfony Cache Item identified by IP
     */
    private function addRemediationToCacheItem(string $ip, string $type, int $expiration, int $decisionId): void
    {
        $item = $this->adapter->getItem($ip);

        // Merge with existing remediations (if any).
        $remediations = $item->isHit() ? $item->get() : [];

        $index = array_search(Constants::REMEDIATION_BYPASS, array_column($remediations, 0));
        if (false !== $index) {
            $this->logger->debug(
                "cache#$ip: Previously clean IP but now bad, remove the " .
                    Constants::REMEDIATION_BYPASS . " remediation immediately"
            );
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

        $item->set($prioritizedRemediations);
        $item->expiresAfter($maxLifetime);

        // Save the cache without committing it to the cache system.
        // Useful to improve performance when updating the cache.
        if (!$this->adapter->saveDeferred($item)) {
            throw new BouncerException(
                "cache#$ip: Unable to save this deferred item in cache: " .
                    "$type for $expiration sec, (decision $decisionId)"
            );
        }
    }

    /**
     * Remove a decision from a Symfony Cache Item identified by ip
     */
    private function removeDecisionFromRemediationItem(string $ip, int $decisionId): bool
    {
        //$this->logger->debug("Remove decision $decisionId from the cache item matching ip ".$ip);
        $item = $this->adapter->getItem($ip);
        $remediations = $item->get();

        $index = false;
        if ($remediations) {
            $index = array_search($decisionId, array_column($remediations, 2));
        }

        // If decision was not found for this cache item early return.
        if (false === $index) {
            return false;
        }
        unset($remediations[$index]);

        if (!$remediations) {
            $this->logger->debug("cache#$ip: No more remediation for cache. Let's remove the cache item");
            $this->adapter->delete($ip);
            return true;
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
        return true;
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
        if (isset($matches[2])) {
            $seconds += ((int) $matches[1]) * 3600; // hours
        }
        if (isset($matches[3])) {
            $seconds += ((int) $matches[2]) * 60; // minutes
        }
        if (isset($matches[4])) {
            $seconds += ((int) $matches[1]); // seconds
        }
        if (isset($matches[5])) { // units in milliseconds
            $seconds *= 0.001;
        }
        if (isset($matches[1])) { // negative
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
            $decision['type'], // ex: ban, captcha
            time() + self::parseDurationToSeconds($decision['duration']), // expiration timestamp
            $decision['id'],
        ];
    }

    private function defferUpdateCacheConfig(array $config): void
    {
        $cacheConfigItem = $this->adapter->getItem('cacheConfig');
        $cacheConfig = $cacheConfigItem->isHit() ? $cacheConfigItem->get() : [];
        $cacheConfig = array_replace_recursive($cacheConfig, $config);
        $cacheConfigItem->set($cacheConfig);
        $this->adapter->saveDeferred($cacheConfigItem);
    }

    /**
     * Update the cached remediations from these new decisions.
     */
    private function saveRemediations(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            if (is_int($decision['start_ip']) && is_int($decision['end_ip'])) {
                $ipRange = array_map('long2ip', range($decision['start_ip'], $decision['end_ip']));
                $remediation = $this->formatRemediationFromDecision($decision);
                foreach ($ipRange as $ip) {
                    $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
                }
            }
        }

        $warmedUp = $this->adapter->commit();

        // Store the fact that the cache has been warmed up.
        $this->defferUpdateCacheConfig(['warmed_up' => $warmedUp]);

        return $warmedUp;
    }

    private function removeRemediations(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            if (is_int($decision['start_ip']) && is_int($decision['end_ip'])) {
                $ipRange = array_map('long2ip', range($decision['start_ip'], $decision['end_ip']));
                $this->logger->debug('decision#' . $decision['id'] . ': remove for IPs ' . join(', ', $ipRange));
                $success = true;
                foreach ($ipRange as $ip) {
                    if (!$this->removeDecisionFromRemediationItem($ip, $decision['id'])) {
                        $success = false;
                    }
                }
                if (!$success) {
                    // The API may return stale deletion events due to API design.
                    // Ignoring them is therefore not a problem.
                    $this->logger->debug("Decision " . $decision['id'] . " not found in cache for one or more items.");
                }
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
                if (!in_array($decision['type'], Constants::ORDERED_REMEDIATIONS)) {
                    $highestRemediationLevel = Constants::ORDERED_REMEDIATIONS[0];
                    // TODO P1 test the case of unknown remediation type
                    $this->logger->warning("The remediation type " . $decision['type'] . " is unknown by this CrowdSec Bouncer version. Fallback to highest remedition level: " . $highestRemediationLevel);
                    $decision['type'] = $highestRemediationLevel;
                }
                $remediation = $this->formatRemediationFromDecision($decision);
                $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
            }
        } else {
            $remediation = $this->formatRemediationFromDecision(null);
            $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
        }
        $this->adapter->commit();
    }

    public function clear(): bool
    {
        return $this->adapter->clear();
    }

    /**
     * Used in stream mode only.
     * Warm the cache up.
     * Used when the stream mode has just been activated.
     *
     */
    private function warmUp(): void
    {
        $this->logger->info('Warming the cache up');
        $startup = true;
        $decisionsDiff = $this->apiClient->getStreamedDecisions($startup);
        $newDecisions = $decisionsDiff['new'];

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
     * 
     * TODO P2 test for overlapping decisions strategy (ex: max expires)
     */
    public function pullUpdates(): void
    {
        $this->logger->info('Pulling updates from API');
        if (!$this->warmedUp) {
            $this->warmUp();
        }

        $decisionsDiff = $this->apiClient->getStreamedDecisions();
        $newDecisions = $decisionsDiff['new'];
        $deletedDecisions = $decisionsDiff['deleted'];

        if ($deletedDecisions) {
            $this->removeRemediations($deletedDecisions);
        }

        if ($newDecisions) {
            $this->saveRemediations($newDecisions);
            if (!$this->warmedUp) {
                throw new BouncerException("Unable to warm the cache up");
            }
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
        // TODO P1 foreach $remediations, control if exp date is not expired.
        // If true, update cache item by removing this expired remediation.

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
        $this->logger->debug('IP to check: ' . $ip);
        if (!$this->liveMode && !$this->warmedUp) {
            throw new BouncerException(
                'CrowdSec Bouncer configured in "stream" mode. Please warm the cache up before trying to access it.'
            );
        }

        if ($this->adapter->hasItem($ip)) {
            $this->logger->debug("Cache hit for IP: $ip");
            return $this->hit($ip);
        } else {
            $this->logger->debug("Cache miss for IP: $ip");
            return $this->miss($ip);
        }
    }

    public function prune(): bool
    {
        $isPrunable = ($this->adapter instanceof PruneableInterface);
        if (!$isPrunable) {
            throw new BouncerException("Cache Adapter" . get_class($this->adapter) . " is not prunable.");
        }
        /** @var PruneableInterface */
        $adapter = $this->adapter;
        $pruned = $adapter->prune();
        $this->logger->info('Cached adapter pruned');

        // TODO P3 Prune remediation inside cache items.
        return $pruned;
    }
}

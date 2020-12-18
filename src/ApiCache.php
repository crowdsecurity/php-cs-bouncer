<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use \DateTime;

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

    /** @var int */
    private $cacheExpirationForBadIp;

    /** @var ApiClient */
    private $apiClient;

    /** @var bool */
    private $warmedUp;

    public function __construct(LoggerInterface $logger, ApiClient $apiClient = null, AbstractAdapter $adapter = null)
    {
        $this->logger = $logger;
        $this->apiClient = $apiClient ?: new ApiClient($logger);
        $this->adapter = $adapter ?: new FilesystemAdapter();
    }

    /**
     * Configure this instance.
     */
    public function configure(
        bool $liveMode,
        string $apiUrl,
        int $timeout,
        string $userAgent,
        string $apiKey,
        int $cacheExpirationForCleanIp,
        int $cacheExpirationForBadIp
    ): void {
        $this->liveMode = $liveMode;
        $this->cacheExpirationForCleanIp = $cacheExpirationForCleanIp;
        $this->cacheExpirationForBadIp = $cacheExpirationForBadIp;
        $cacheConfigItem = $this->adapter->getItem('cacheConfig');
        $cacheConfig = $cacheConfigItem->get();
        $this->warmedUp = (is_array($cacheConfig) && isset($cacheConfig['warmed_up'])
            && $cacheConfig['warmed_up'] === true);
        $this->logger->debug(null, [
            'type' => 'API_CACHE_INIT',
            'adapter' => get_class($this->adapter),
            'mode' => ($liveMode ? 'live' : 'stream'),
            'exp_clean_ips' => $cacheExpirationForCleanIp,
            'exp_bad_ips' => $cacheExpirationForBadIp,
            'warmed_up' => ($this->warmedUp ? 'true' : 'false'),
        ]);
        $this->apiClient->configure($apiUrl, $timeout, $userAgent, $apiKey);
    }

    /**
     * Add remediation to a Symfony Cache Item identified by IP
     */
    private function addRemediationToCacheItem(string $ip, string $type, int $expiration, int $decisionId): string
    {
        $item = $this->adapter->getItem($ip);

        // Merge with existing remediations (if any).
        $remediations = $item->isHit() ? $item->get() : [];

        $index = array_search(Constants::REMEDIATION_BYPASS, array_column($remediations, 0));
        if (false !== $index) {
            $this->logger->debug(null, [
                'type' => 'IP_CLEAN_TO_BAD',
                'ip' => $ip,
                'old_remediation' => Constants::REMEDIATION_BYPASS,
            ]);
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
        $item->expiresAt(new DateTime('@' . $maxLifetime));

        // Save the cache without committing it to the cache system.
        // Useful to improve performance when updating the cache.
        if (!$this->adapter->saveDeferred($item)) {
            throw new BouncerException(
                "cache#$ip: Unable to save this deferred item in cache: " .
                    "$type for $expiration sec, (decision $decisionId)"
            );
        }
        return $prioritizedRemediations[0][0];
    }

    /**
     * Remove a decision from a Symfony Cache Item identified by ip
     */
    private function removeDecisionFromRemediationItem(string $ip, int $decisionId): bool
    {
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
            $this->logger->debug(null, [
                'type' => 'CACHE_ITEM_REMOVED',
                'ip' => $ip
            ]);
            $this->adapter->delete($ip);
            return true;
        }
        // Build the item lifetime in cache and sort remediations by priority
        $maxLifetime = max(array_column($remediations, 1));
        $cacheContent = Remediation::sortRemediationByPriority($remediations);
        $item->expiresAt(new DateTime('@' . $maxLifetime));
        $item->set($cacheContent);

        // Save the cache without commiting it to the cache system.
        // Useful to improve performance when updating the cache.
        if (!$this->adapter->saveDeferred($item)) {
            throw new BouncerException("cache#$ip: Unable to save item");
        }
        $this->logger->debug(null, [
            'type' => 'DECISION_REMOVED',
            'decision' => $decisionId,
            'ips' => [$ip]
        ]);
        return true;
    }

    /**
     * Parse "duration" entries returned from API to a number of seconds.
     */
    private static function parseDurationToSeconds(string $duration): int
    {
        $re = '/(-?)(?:(?:(\d+)h)?(\d+)m)?(\d+).\d+(m?)s/m';
        preg_match($re, $duration, $matches);
        if (!count($matches)) {
            throw new BouncerException("Unable to parse the following duration: ${$duration}.");
        }
        $seconds = 0;
        if (isset($matches[2])) {
            $seconds += ((int) $matches[2]) * 3600; // hours
        }
        if (isset($matches[3])) {
            $seconds += ((int) $matches[3]) * 60; // minutes
        }
        if (isset($matches[4])) {
            $seconds += ((int) $matches[4]); // seconds
        }
        if ('m' === ($matches[5])) { // units in milliseconds
            $seconds *= 0.001;
        }
        if ("-" === ($matches[1])) { // negative
            $seconds *= -1;
        }

        return (int)round($seconds);
    }



    /**
     * Format a remediation item of a cache item.
     * This format use a minimal amount of data allowing less cache data consumption.
     */
    private function formatRemediationFromDecision(?array $decision): array
    {
        if (!$decision) {
            return [Constants::REMEDIATION_BYPASS, time() + $this->cacheExpirationForCleanIp, 0];
        }

        $duration = min($this->cacheExpirationForBadIp, self::parseDurationToSeconds($decision['duration']));

        return [
            $decision['type'], // ex: ban, captcha
            time() + $duration, // expiration timestamp
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
        return $this->adapter->commit();
    }

    private function removeRemediations(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            if (is_int($decision['start_ip']) && is_int($decision['end_ip'])) {
                $ipRange = array_map('long2ip', range($decision['start_ip'], $decision['end_ip']));
                $this->logger->debug(null, [
                    'type' => 'DECISION_REMOVED', 'decision' => $decision['id'], 'ips' => $ipRange
                ]);
                $success = true;
                foreach ($ipRange as $ip) {
                    if (!$this->removeDecisionFromRemediationItem($ip, $decision['id'])) {
                        $success = false;
                    }
                }
                if (!$success) {
                    // The API may return stale deletion events due to API design.
                    // Ignoring them is therefore not a problem.
                    $this->logger->debug(null, ['type' => 'DECISION_TO_REMOVE_NOT_FOUND_IN_CACHE', 'decision' => $decision['id']]);
                }
            }
        }
        return $this->adapter->commit();
    }

    /**
     * Update the cached remediation of the specified IP from these new decisions.
     */
    private function saveRemediationsForIp(array $decisions, string $ip): string
    {
        $remediationResult = Constants::REMEDIATION_BYPASS;
        if (\count($decisions)) {
            foreach ($decisions as $decision) {
                if (!in_array($decision['type'], Constants::ORDERED_REMEDIATIONS)) {
                    $highestRemediationLevel = Constants::ORDERED_REMEDIATIONS[0];
                    // TODO P1 test the case of unknown remediation type
                    $this->logger->warning(null, ['type' => 'UNKNOWN_REMEDIATION', 'remediation' => $decision['type']]);
                    // TODO P2 use the fallback parameter instead.
                    $decision['type'] = $highestRemediationLevel;
                }
                $remediation = $this->formatRemediationFromDecision($decision);
                $remediationResult = $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
            }
        } else {
            $remediation = $this->formatRemediationFromDecision(null);
            $remediationResult = $this->addRemediationToCacheItem($ip, $remediation[0], $remediation[1], $remediation[2]);
        }
        $this->adapter->commit();
        return $remediationResult;
    }

    public function clear(): bool
    {
        $cleared = $this->adapter->clear();
        $this->warmedUp = false;
        $this->defferUpdateCacheConfig(['warmed_up' => $this->warmedUp]);
        $this->adapter->commit();
        $this->logger->info(null, ['type' => 'CACHE_CLEARED']);
        return $cleared;
    }

    /**
     * Used in stream mode only.
     * Warm the cache up.
     * Used when the stream mode has just been activated.
     * 
     * @return int number of decisions added.
     *
     */
    public function warmUp(): int
    {
        if ($this->warmedUp) {
            $this->clear();
        }
        $this->logger->debug(null, ['type' => 'START_CACHE_WARMUP']);
        $startup = true;
        $decisionsDiff = $this->apiClient->getStreamedDecisions($startup);
        $newDecisions = $decisionsDiff['new'];

        $nbNew = 0;
        if ($newDecisions) {
            $this->warmedUp = $this->saveRemediations($newDecisions);
            $this->defferUpdateCacheConfig(['warmed_up' => $this->warmedUp]);
            $this->adapter->commit();
            if (!$this->warmedUp) {
                throw new BouncerException("Unable to warm the cache up");
            }
            $nbNew = count($newDecisions);
        }

        // Store the fact that the cache has been warmed up.
        $this->defferUpdateCacheConfig(['warmed_up' => true]);

        $this->adapter->commit();
        $this->logger->info(null, ['type' => 'CACHE_WARMED_UP', 'added_decisions' => $nbNew]);
        return $nbNew;
    }

    /**
     * Used in stream mode only.
     * Pull decisions updates from the API and update the cached remediations.
     * Used for the stream mode when we have to update the remediations list.
     * 
     * @return array number of deleted and new decisions.
     * 
     */
    public function pullUpdates(): array
    {
        if (!$this->warmedUp) {
            return ['deleted' => 0, 'new' => $this->warmUp()];
        }

        $this->logger->debug(null, ['type' => 'START_CACHE_UPDATE']);
        $decisionsDiff = $this->apiClient->getStreamedDecisions();
        $newDecisions = $decisionsDiff['new'];
        $deletedDecisions = $decisionsDiff['deleted'];

        $nbDeleted = 0;
        if ($deletedDecisions) {
            $this->removeRemediations($deletedDecisions);
            $nbDeleted = count($deletedDecisions);
        }

        $nbNew = 0;
        if ($newDecisions) {
            $this->saveRemediations($newDecisions);
            $nbNew = count($newDecisions);
        }

        $this->logger->debug(null, ['type' => 'CACHE_UPDATED', 'deleted' => $nbDeleted, 'new' => $nbNew]);
        return ['deleted' => $nbDeleted, 'new' => $nbNew];
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
            $this->logger->debug(null, ['type' => 'DIRECT_API_CALL', 'ip' => $ip]);
            $decisions = $this->apiClient->getFilteredDecisions(['ip' => $ip]);
        }

        return $this->saveRemediationsForIp($decisions, $ip);
    }

    /**
     * Used in both mode (stream and ruptue).
     * This method formats the cached item as a remediation.
     * It returns the highest remediation level found.
     */
    private function hit(string $ip): string
    {
        $remediations = $this->adapter->getItem($ip)->get();

        // We apply array values first because keys are ids.
        $firstRemediation = array_values($remediations)[0];

        /** @var string */
        return $firstRemediation[0];
    }

    /**
     * Request the cache for the specified IP.
     *
     * @return string the computed remediation string, or null if no decision was found
     */
    public function get(string $ip): string
    {
        $this->logger->debug(null, ['type' => 'START_IP_CHECK', 'ip' => $ip]);
        if (!$this->liveMode && !$this->warmedUp) {
            throw new BouncerException(
                'CrowdSec Bouncer configured in "stream" mode. Please warm the cache up before trying to access it.'
            );
        }

        if ($this->adapter->hasItem($ip)) {
            $remediation = $this->hit($ip);
            $cache = 'hit';
        } else {
            $remediation = $this->miss($ip);
            $cache = 'miss';
        }

        if ($remediation === Constants::REMEDIATION_BYPASS) {
            $this->logger->info(null, ['type' => 'CLEAN_IP', 'ip' => $ip, 'cache' => $cache]);
        } else {
            $this->logger->warning(null, ['type' => 'BAD_IP', 'ip' => $ip, 'remediation' => $remediation, 'cache' => $cache]);
        }

        return $remediation;
    }

    public function prune(): bool
    {
        if ($this->adapter instanceof PruneableInterface) {
            $pruned = $this->adapter->prune();
            $this->logger->debug(null, ['type' => 'CACHE_PRUNED']);
            return $pruned;
        }
        
        throw new BouncerException("Cache Adapter" . get_class($this->adapter) . " is not prunable.");
    }
}

<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * The cache mecanism to store every decisions from LAPI/CAPI. Symfony Cache component powered.
 * 
 * @author    CrowdSec team
 * @link      https://crowdsec.net CrowdSec Official Website
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class ApiCache
{
    /** @var AbstractAdapter */
    private $adapter;

    /** @var bool */
    private $ruptureMode;

    /** @var ApiClient */
    private $apiClient;

    /** @var bool */
    private $warmedUp = false;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient ?: new ApiClient();
    }

    /**
     * Configure this instance.
     */
    public function configure(AbstractAdapter $adapter, bool $ruptureMode, array $apiClientConfiguration)
    {
        $this->adapter = $adapter ?: new NullAdapter();
        $this->ruptureMode = $ruptureMode;

        $this->apiClient->configure(
            $apiClientConfiguration['api_url'],
            $apiClientConfiguration['api_timeout'],
            $apiClientConfiguration['api_user_agent'],
            $apiClientConfiguration['api_token']
        );
    }

    /**
     * Build a Symfony Cache Item from a couple of IP and its computed remediation
     */
    private function buildRemediationCacheItem(int $ip, array $remediation): CacheItem
    {
        $item = $this->adapter->getItem((string)$ip);
        
        // Merge with existing remediations (if any).
        $remediations = $item->get();
        $remediations = $remediations ?: [];
        $remediations[$remediation[2]] = $remediation;// erase previous decision with the same id
        
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
    private function saveDeferred(CacheItem $item, int $ip, array $remediation): void
    {
        $isQueued = $this->adapter->saveDeferred($item);
        if (!$isQueued) {
            $ipStr = long2Ip($ip);
            throw new BouncerException(`Unable to save this deferred item in cache: ${$ipStr} =>$remediation[0] (for $remediation[1]sec)`);
        }
    }

    /*

    Update the cached remediations from these new decisions.

    TODO WRITE TESTS P2
    0 decisions
    3 known remediation type
    3 decisions but 1 unknown remediation type
    3 unknown remediation type
     */
    private function saveRemediations(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            $ipRange = range($decision['start_ip'], $decision['end_ip']);
            foreach ($ipRange as $ip) {
                $remediation = Remediation::formatFromDecision($decision);
                $item = $this->buildRemediationCacheItem($ip, $remediation);
                $this->saveDeferred($item, $ip, $remediation);
            }
        }
        return $this->adapter->commit();
    }

    /**
     * Update the cached remediation of the specified IP from these new decisions.
     */
    private function saveRemediationsForIp(array $decisions, int $ip): void
    {
        foreach ($decisions as $decision) {
                $remediation = Remediation::formatFromDecision($decision);
                $item = $this->buildRemediationCacheItem($ip, $remediation);
                $this->saveDeferred($item, $ip, $remediation);
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
        $startup = true;
        $decisionsDiff = $this->apiClient->getStreamedDecisions($startup);
        $newDecisions = $decisionsDiff['new'];

        $this->adapter->clear();

        if ($newDecisions) {
            $this->warmedUp = $this->saveRemediations($newDecisions);
            if (!$this->warmedUp) {
                throw new BouncerException(`Unable to warm the cache up`);
            }
        }
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

        if (!count($decisions)) {
            // TODO P1 cache also the clean IP.
            return Remediation::formatFromDecision(null)[0];
        }

        $this->saveRemediationsForIp($decisions, $ip);

        return $this->hit($ip);
    }


    /**
     * Used in both mode (stream and ruptue).
     * This method formats the cached item as a remediation.
     * It returns the highest remediation level found.
     */
    private function hit(int $ip): ?string
    {
        $remediations = $this->adapter->getItem((string)$ip)->get();
        // P2 TODO control before if date is not expired and if true, update cache item.
        return $remediations[0][0]; // 0: first remediation level, 0: the "type" string
    }

    /**
     * Request the cache for the specified IP.
     * 
     * @return string The computed remediation string, or null if no decision was found.
     */
    public function get(int $ip): ?string
    {
        if (!$this->ruptureMode && !$this->warmedUp) {
            throw new BouncerException('CrowdSec Bouncer configured in "stream" mode. Please warm the cache up before trying to access it.');
        }

        if ($this->adapter->hasItem((string)$ip)) {
            return $this->hit($ip);
        } else if ($this->ruptureMode) {
            return $this->miss($ip);
        }
        return Remediation::formatFromDecision(null)[0];
    }
}

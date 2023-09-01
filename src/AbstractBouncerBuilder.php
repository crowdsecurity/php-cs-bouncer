<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use CrowdSec\CapiClient\Client\CapiHandler\Curl as CapiCurl;
use CrowdSec\CapiClient\Client\CapiHandler\FileGetContents as CapiFileGetContents;
use CrowdSec\CapiClient\Storage\StorageInterface;
use CrowdSec\CapiClient\Watcher as WatcherClient;
use CrowdSec\Common\Client\AbstractClient;
use CrowdSec\Common\Client\RequestHandler\Curl;
use CrowdSec\Common\Client\RequestHandler\FileGetContents;
use CrowdSec\LapiClient\Bouncer as BouncerClient;
use CrowdSec\RemediationEngine\AbstractRemediation;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSec\RemediationEngine\CapiRemediation;
use Psr\Log\LoggerInterface;

/**
 * The class to create a LAPI or CAPI bouncer.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
abstract class AbstractBouncerBuilder extends AbstractBouncer
{
    public function __construct(array $configs, StorageInterface $storage = null, LoggerInterface $logger = null)
    {
        $remediationEngine = $this->buildRemediationEngine($configs, $storage, $logger);
        parent::__construct($configs, $remediationEngine, $logger);
    }

    private function buildRemediationEngine(
        array $configs,
        StorageInterface $storage = null,
        LoggerInterface $logger = null
    ): AbstractRemediation {
        $cache = $this->handleCache($configs, $logger);

        $client = $this->buildClient($configs, $storage, $logger);

        if ($client instanceof WatcherClient) {
            return new CapiRemediation($configs, $client, $cache, $logger);
        }

        return new LapiRemediation($configs, $client, $cache, $logger);
    }

    private function buildClient(
        array $configs,
        StorageInterface $storage = null,
        LoggerInterface $logger = null
    ): AbstractClient {
        $useCurl = !empty($configs['use_curl']);

        if ($storage) {
            $requestHandler = $useCurl ? new CapiCurl($configs) : new CapiFileGetContents($configs);

            return new WatcherClient($configs, $storage, $requestHandler, $logger);
        }

        $requestHandler = $useCurl ? new Curl($configs) : new FileGetContents($configs);

        return new BouncerClient($configs, $requestHandler, $logger);
    }
}

<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use CrowdSec\CapiClient\Client\CapiHandler\Curl;
use CrowdSec\CapiClient\Client\CapiHandler\FileGetContents;
use CrowdSec\CapiClient\Storage\StorageInterface;
use CrowdSec\CapiClient\Watcher as WatcherClient;
use CrowdSec\RemediationEngine\CapiRemediation;
use Psr\Log\LoggerInterface;

/**
 * The class that apply a bounce from CAPI remediation.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
abstract class AbstractCapiBouncer extends AbstractBouncer
{
    public function __construct(array $configs, StorageInterface $storage, LoggerInterface $logger = null)
    {
        $remediationEngine = $this->buildRemediationEngine($configs, $storage, $logger);
        parent::__construct($configs, $remediationEngine, $logger);
    }

    private function buildRemediationEngine(
        array $configs,
        StorageInterface $storage,
        LoggerInterface $logger = null
    ): CapiRemediation {
        $client = $this->buildClient($configs, $storage, $logger);
        $cache = $this->handleCache($configs, $logger);

        return new CapiRemediation($configs, $client, $cache, $logger);
    }

    private function buildClient(
        array $configs,
        StorageInterface $storage,
        LoggerInterface $logger = null
    ): WatcherClient {
        $requestHandler = empty($configs['use_curl']) ? new FileGetContents($configs) : new Curl($configs);

        return new WatcherClient($configs, $storage, $requestHandler, $logger);
    }
}

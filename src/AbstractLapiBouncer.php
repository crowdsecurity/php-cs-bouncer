<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

use CrowdSec\Common\Client\RequestHandler\Curl;
use CrowdSec\Common\Client\RequestHandler\FileGetContents;
use CrowdSec\LapiClient\Bouncer as BouncerClient;
use CrowdSec\RemediationEngine\LapiRemediation;
use Psr\Log\LoggerInterface;

/**
 * The class that apply a bounce from LAPI remediation.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
abstract class AbstractLapiBouncer extends AbstractBouncer
{
    public function __construct(array $configs, LoggerInterface $logger = null)
    {
        $remediationEngine = $this->buildRemediationEngine($configs, $logger);
        parent::__construct($configs, $remediationEngine, $logger);
    }

    private function buildRemediationEngine(
        array $configs,
        LoggerInterface $logger = null
    ): LapiRemediation {
        $client = $this->buildClient($configs, $logger);
        $cache = $this->handleCache($configs, $logger);

        return new LapiRemediation($configs, $client, $cache, $logger);
    }

    private function buildClient(array $configs, LoggerInterface $logger = null): BouncerClient
    {
        $requestHandler = empty($configs['use_curl']) ? new FileGetContents($configs) : new Curl($configs);

        return new BouncerClient($configs, $requestHandler, $logger);
    }
}

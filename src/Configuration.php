<?php

namespace CrowdSecBouncer;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The Library configuration. You'll find here all configuration possible. Used when instanciating the library.
 * 
 * @author    CrowdSec team
 * @link      https://crowdsec.net CrowdSec Official Website
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('config');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->scalarNode('api_token')->isRequired()->end()
            ->scalarNode('api_url')->defaultValue(Constants::CAPI_URL)->end()
            ->scalarNode('api_user_agent')->defaultValue(Constants::BASE_USER_AGENT)->end()
            ->integerNode('api_timeout')->defaultValue(Constants::API_TIMEOUT)->end()
            ->booleanNode('rupture_mode')->defaultValue(true)->end()
            ->enumNode('max_remediation')->values(['bypass', 'captcha', 'ban'])->defaultValue('ban')->end()
            ->end();
        return $treeBuilder;
    }
}

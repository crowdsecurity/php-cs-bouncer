<?php

namespace CrowdSecBouncer;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The Library configuration. You'll find here all configuration possible. Used when instantiating the library.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('config');
        /** @var $rootNode ArrayNodeDefinition */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('api_key')->isRequired()->end()
                ->scalarNode('api_url')->defaultValue(Constants::CAPI_URL)->end()
                ->scalarNode('api_user_agent')->defaultValue(Constants::BASE_USER_AGENT)->end()
                ->integerNode('api_timeout')->defaultValue(Constants::API_TIMEOUT)->end()
                ->booleanNode('live_mode')->defaultValue(true)->end()
                ->scalarNode('forced_test_ip')->end()
                ->enumNode('max_remediation_level')
                    ->values(Constants::ORDERED_REMEDIATIONS)
                    ->defaultValue(Constants::REMEDIATION_BAN)
                ->end()
                    ->enumNode('fallback_remediation')
                    ->values(Constants::ORDERED_REMEDIATIONS)
                    ->defaultValue(Constants::REMEDIATION_CAPTCHA)
                ->end()
                ->integerNode('cache_expiration_for_clean_ip')
                    ->defaultValue(Constants::CACHE_EXPIRATION_FOR_CLEAN_IP)
                ->end()
                ->integerNode('cache_expiration_for_bad_ip')
                    ->defaultValue(Constants::CACHE_EXPIRATION_FOR_BAD_IP)
                ->end()
                ->arrayNode('geolocation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('save_in_session')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('type')
                            ->defaultValue(Constants::GEOLOCATION_TYPE_MAXMIND)
                            ->values([Constants::GEOLOCATION_TYPE_MAXMIND])
                        ->end()
                        ->arrayNode(Constants::GEOLOCATION_TYPE_MAXMIND)
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('database_type')
                                    ->defaultValue(Constants::MAXMIND_COUNTRY)
                                    ->values([Constants::MAXMIND_COUNTRY, Constants::MAXMIND_CITY])
                                ->end()
                                ->scalarNode('database_path')
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

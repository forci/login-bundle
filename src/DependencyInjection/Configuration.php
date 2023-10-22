<?php

/*
 * This file is part of the ForciLoginBundle package.
 *
 * Copyright (c) Forci Web Consulting Ltd.
 *
 * Author Martin Kirilov <martin@forci.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Forci\Bundle\LoginBundle\DependencyInjection;

use Forci\Bundle\LoginBundle\Helper\LoginHelper;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface {

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('forci_login');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('managers')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('class')
                                ->defaultValue(LoginHelper::class)
                                ->validate()
                                    ->ifTrue(function ($class) {
                                        return !is_a($class, LoginHelper::class, true);
                                    })->thenInvalid(sprintf('Configuration "class" must be a child of %s, "%%s" provided', LoginHelper::class))
                                ->end()
                            ->end()
                            ->scalarNode('public')->defaultFalse()->end()
                            ->scalarNode('firewall_name')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

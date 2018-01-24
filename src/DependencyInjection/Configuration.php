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

use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Forci\Bundle\LoginBundle\Manager\LoginManager;

class Configuration implements ConfigurationInterface {

    public function getConfigTreeBuilder() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('forci_login');

        $rootNode
            ->children()
                ->arrayNode('managers')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('class')
                                ->defaultValue(LoginManager::class)
                                ->validate()
                                    ->ifTrue(function ($class) {
                                        return !is_a($class, LoginManager::class, true);
                                    })->thenInvalid(sprintf('Configuration "class" must be a child of %s, "%%s" provided', LoginManager::class))
                                ->end()
                            ->end()
                            ->scalarNode('remember_me')->defaultTrue()->end()
                            ->scalarNode('public')->defaultFalse()->end()
                            ->scalarNode('firewall_name')->isRequired()->end()
                            ->arrayNode('hwi_oauth')
                                ->canBeEnabled()
                                ->children()
                                    ->scalarNode('token_class')
                                        ->defaultValue(OAuthToken::class)
                                        ->validate()
                                            ->ifTrue(function ($class) {
                                                return !is_a($class, OAuthToken::class, true);
                                            })->thenInvalid(sprintf('Configuration "token_class" must be a child of %s, %%s provided', OAuthToken::class))
                                        ->end()
                                    ->end()
                                    ->scalarNode('use_username_password_token')->defaultFalse()->end()
                                    ->scalarNode('always_authenticated')->defaultFalse()->end()
                                    ->scalarNode('user_provider')->defaultValue('hwi_oauth.user.provider')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

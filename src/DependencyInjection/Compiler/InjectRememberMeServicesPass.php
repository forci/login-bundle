<?php

/*
 * This file is part of the ForciLoginBundle package.
 *
 * (c) Martin Kirilov <wucdbm@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Forci\Bundle\LoginBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class InjectRememberMeServicesPass implements CompilerPassInterface {

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container) {
        foreach ($container->getParameter('forci_login_manager.config')['managers'] as $name => $config) {
            $managerDefinition = new ChildDefinition('forci_login.manager.abstract');
            $managerDefinition->setClass($config['class']);
            $managerDefinition->replaceArgument(5, $config);
            $managerDefinition->setPublic(true);

            $firewallName = $config['firewall_name'];

            if ($config['remember_me']) {
                if ($container->hasDefinition('security.authentication.rememberme.services.persistent.'.$firewallName)) {
                    $managerDefinition->addMethodCall('setRememberMeService', [new Reference('security.authentication.rememberme.services.persistent.'.$firewallName)]);
                } elseif ($container->hasDefinition('security.authentication.rememberme.services.simplehash.'.$firewallName)) {
                    $managerDefinition->addMethodCall('setRememberMeService', [new Reference('security.authentication.rememberme.services.simplehash.'.$firewallName)]);
                }
            }

            if ($config['hwi_oauth']['enabled']) {
                $managerDefinition->addMethodCall('setHwiOAuthResourceOwnerMap', [new Reference(sprintf('hwi_oauth.resource_ownermap.%s', $firewallName))]);
                $managerDefinition->addMethodCall('setHwiOauthUserProvider', [new Reference($config['hwi_oauth']['user_provider'])]);
            }

            $managerId = sprintf('forci_login.manager.%s', $name);
            $container->setDefinition($managerId, $managerDefinition);
        }
    }
}

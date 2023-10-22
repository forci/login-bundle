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

namespace Forci\Bundle\LoginBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class InjectRememberMeServicesPass implements CompilerPassInterface {

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getParameter('forci_login.config')['managers'] as $name => $config) {
            $managerDefinition = new ChildDefinition('forci_login.helper.abstract');
            $managerDefinition->setClass($config['class']);
            $managerDefinition->replaceArgument(5, $config);
            $managerDefinition->setPublic(true);

            $firewallName = $config['firewall_name'];

            if ($container->hasDefinition('security.authentication.rememberme.services.persistent.'.$firewallName)) {
                $managerDefinition->addMethodCall('setRememberMeService', [new Reference('security.authentication.rememberme.services.persistent.'.$firewallName)]);
            } elseif ($container->hasDefinition('security.authentication.rememberme.services.simplehash.'.$firewallName)) {
                $managerDefinition->addMethodCall('setRememberMeService', [new Reference('security.authentication.rememberme.services.simplehash.'.$firewallName)]);
            }

            $managerId = sprintf('forci_login.helper.%s', $name);
            $container->setDefinition($managerId, $managerDefinition);
        }
    }
}

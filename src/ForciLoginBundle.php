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

namespace Forci\Bundle\LoginBundle;

use Forci\Bundle\LoginBundle\DependencyInjection\Compiler\InjectRememberMeServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ForciLoginBundle extends Bundle {

    public function build(ContainerBuilder $container) {
        parent::build($container);

        $container->addCompilerPass(new InjectRememberMeServicesPass());
    }
}

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

namespace Forci\Bundle\LoginBundle\Helper;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class SilentLoginHelper {

    /** @var RequestStack */
    protected $requestStack;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /**
     * SilentLoginManager constructor.
     *
     * @param RequestStack             $requestStack
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(RequestStack $requestStack, EventDispatcherInterface $eventDispatcher) {
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param AdvancedUserInterface $user    Your User object
     * @param string                $area    This is the firewall NAME
     * @param string                $context This is the firewall CONTEXT
     */
    public function login(AdvancedUserInterface $user, $area, $context) {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return;
        }

        $token = new UsernamePasswordToken($user, $user->getPassword(), $area, $user->getRoles());

        $session = $request->getSession();
        // This one uses the CONTEXT NAME
        $session->set('_security_'.$context, serialize($token));
        // This one uses the FIREWALL NAME
        // This can be used to set the address the user should be redirected to, but is rather useless in this situation?
        // $session->set('_security.'.$area.'.target_path', 'https://website.com/app_dev.php/some/address');

        $event = new InteractiveLoginEvent($request, $token);
        $this->eventDispatcher->dispatch('security.interactive_login', $event);
    }
}

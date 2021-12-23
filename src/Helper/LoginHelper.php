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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

class LoginHelper {

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var UserCheckerInterface */
    private $userChecker;

    /** @var SessionAuthenticationStrategyInterface */
    private $sessionStrategy;

    /** @var RequestStack */
    private $requestStack;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var RememberMeHandlerInterface|null */
    private $rememberMeService;

    /** @var array */
    private $config;

    public function __construct(
        TokenStorageInterface $tokenStorage, UserCheckerInterface $userChecker,
        SessionAuthenticationStrategyInterface $sessionStrategy,
        RequestStack $requestStack, EventDispatcherInterface $eventDispatcher,
        array $config
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->userChecker = $userChecker;
        $this->sessionStrategy = $sessionStrategy;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = $config;
    }

    public function getFirewallName(): string {
        return $this->config['firewall_name'];
    }

    final public function logInUser(UserInterface $user, bool $preAuthenticated = false): void {
        $this->logInToken($this->createToken($user, $preAuthenticated));
    }

    final public function rememberUser(UserInterface $user, Response $response, bool $preAuthenticated = false): void {
        $this->logInToken($this->createToken($user, $preAuthenticated), [
            'remember_me' => $response
        ]);
    }

    protected function logInToken(TokenInterface $token, array $options = []): void {
        $user = $token->getUser();

        if ($user) {
            try {
                $this->userChecker->checkPreAuth($user);
                $this->userChecker->checkPostAuth($user);
            } catch (AccountStatusException $e) {
                // Don't authenticate locked, disabled or expired users
                return;
            }
        }

        $this->tokenStorage->setToken($token);

        $request = $this->requestStack->getCurrentRequest();

        $this->callSessionAuthenticationStrategy($token, $request);

        if (isset($options['remember_me'])) {
            $response = $options['remember_me'];

            if ($response instanceof Response) {
                $this->callRememberMeServices($token);
            }
        }

        $this->dispatchInteractiveLogin($token, $request);
    }

    protected function callSessionAuthenticationStrategy(TokenInterface $token, Request $request = null) {
        if (!$request) {
            return;
        }

        $this->sessionStrategy->onAuthentication($request, $token);
    }

    protected function callRememberMeServices(TokenInterface $token) {
        if (!$this->rememberMeService) {
            return;
        }

        if ($token->getUser()) {
            $this->rememberMeService->createRememberMeCookie($token->getUser());
        }
    }

    protected function dispatchInteractiveLogin(TokenInterface $token, Request $request = null) {
        if (!$request) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new InteractiveLoginEvent($request, $token),
            SecurityEvents::INTERACTIVE_LOGIN
        );
    }

    protected function createUsernamePasswordToken(UserInterface $user): UsernamePasswordToken {
        return new UsernamePasswordToken($user, $this->getFirewallName(), $user->getRoles());
    }

    protected function createPreAuthenticatedToken(UserInterface $user): PreAuthenticatedToken {
        $token = new PreAuthenticatedToken($user, $this->getFirewallName(), $user->getRoles());

        return $token;
    }

    protected function createToken(UserInterface $user, bool $preAuthenticated): AbstractToken {
        if ($preAuthenticated) {
            return $this->createPreAuthenticatedToken($user);
        }

        return $this->createUsernamePasswordToken($user);
    }

    public function getRememberMeService(): ?RememberMeHandlerInterface {
        return $this->rememberMeService;
    }

    public function setRememberMeService(RememberMeHandlerInterface $rememberMeService = null) {
        $this->rememberMeService = $rememberMeService;
    }

}

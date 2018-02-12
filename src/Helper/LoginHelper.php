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

use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Forci\Bundle\LoginBundle\HWIOAuth\OAuthToken;

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

    /** @var RememberMeServicesInterface|null */
    private $rememberMeService;

    /** @var ResourceOwnerMap|null */
    private $hwiOAuthResourceOwnerMap;

    /** @var OAuthAwareUserProviderInterface|null */
    private $hwiOauthUserProvider;

    /** @var array */
    private $config;

    public function __construct(TokenStorageInterface $tokenStorage, UserCheckerInterface $userChecker,
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

    final public function logInUser(UserInterface $user, Response $response = null): void {
        $token = $this->createUsernamePasswordToken($user);

        $this->logInToken($token, $user, $response);
    }

    /**
     * @param string|array $accessToken
     * @param string       $state
     * @param string       $resourceOwner
     * @param Response     $response
     *
     * @throws \InvalidArgumentException When login is not enabled for hwi oauth for this provider
     * @throws \InvalidArgumentException When remember me is turned on, but no Response is provided
     * @throws UsernameNotFoundException When user could not be found
     * @throws AuthenticationException   When state is wrong
     */
    final public function logInHWIOAuthAccessToken($accessToken, string $state, string $resourceOwner, Response $response = null): void {
        if (!$this->hwiOAuthResourceOwnerMap) {
            throw new \InvalidArgumentException(sprintf('HWI OAuth Login called, but is not enabled for "%s"', $this->config['firewall_name']));
        }

        $resourceOwner = $this->hwiOAuthResourceOwnerMap->getResourceOwnerByName($resourceOwner);

        $userResponse = $resourceOwner->getUserInformation($accessToken);

        $user = $this->hwiOauthUserProvider->loadUserByOAuthUserResponse($userResponse);

        $resourceOwner->isCsrfTokenValid($state);

        if ($this->config['hwi_oauth']['use_username_password_token']) {
            $token = $this->createUsernamePasswordToken($user);
        } else {
            $token = $this->createHWIOAuthToken($accessToken, $user, $resourceOwner->getName());
        }

        $this->logInToken($token, $user, $response);
    }

    protected function logInToken(TokenInterface $token, UserInterface $user, Response $response = null) {
        try {
            $this->userChecker->checkPreAuth($user);
            $this->userChecker->checkPostAuth($user);
        } catch (AccountStatusException $e) {
            // Don't authenticate locked, disabled or expired users
            return;
        }

        $this->tokenStorage->setToken($token);

        $request = $this->requestStack->getCurrentRequest();

        $this->callSessionAuthenticationStrategy($token, $request);

        if ($this->config['remember_me']) {
            if (!$response) {
                throw new \InvalidArgumentException('Config value "remember_me" is true, but no Response provider for method call.');
            }

            $this->callRememberMeServices($token, $request, $response);
        }

        $this->dispatchInteractiveLogin($token, $request);
    }

    protected function callSessionAuthenticationStrategy(TokenInterface $token, Request $request = null) {
        if (!$request) {
            return;
        }

        $this->sessionStrategy->onAuthentication($request, $token);
    }

    protected function callRememberMeServices(TokenInterface $token, Request $request = null, Response $response) {
        if (!$request) {
            return;
        }

        if (!$this->rememberMeService) {
            return;
        }

        $this->rememberMeService->loginSuccess($request, $response, $token);
    }

    protected function dispatchInteractiveLogin(TokenInterface $token, Request $request = null) {
        if (!$request) {
            return;
        }

        $this->eventDispatcher->dispatch(
            SecurityEvents::INTERACTIVE_LOGIN,
            new InteractiveLoginEvent($request, $token)
        );
    }

    protected function createUsernamePasswordToken(UserInterface $user): UsernamePasswordToken {
        return new UsernamePasswordToken($user, null, $this->getFirewallName(), $user->getRoles());
    }

    /**
     * @param string|array  $accessToken
     * @param UserInterface $user
     * @param string        $resourceOwnerName
     *
     * @return \HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken
     */
    protected function createHWIOAuthToken($accessToken, UserInterface $user, string $resourceOwnerName) {
        $class = $this->config['hwi_oauth']['token_class'];
        $token = new $class($accessToken, $user->getRoles());

        if ($token instanceof OAuthToken) {
            $token->setAlwaysAuthenticated($this->config['hwi_oauth']['always_authenticated']);
            $token->setProviderKey($this->getFirewallName());
        }

        $token->setResourceOwnerName($resourceOwnerName);
        $token->setUser($user);

        return $token;
    }

    public function getRememberMeService(): ?RememberMeServicesInterface {
        return $this->rememberMeService;
    }

    public function setRememberMeService(RememberMeServicesInterface $rememberMeService = null) {
        $this->rememberMeService = $rememberMeService;
    }

    public function getHwiOAuthResourceOwnerMap(): ?ResourceOwnerMap {
        return $this->hwiOAuthResourceOwnerMap;
    }

    public function setHwiOAuthResourceOwnerMap(ResourceOwnerMap $hwiOAuthResourceOwnerMap = null) {
        $this->hwiOAuthResourceOwnerMap = $hwiOAuthResourceOwnerMap;
    }

    public function getHwiOauthUserProvider(): ?OAuthAwareUserProviderInterface {
        return $this->hwiOauthUserProvider;
    }

    public function setHwiOauthUserProvider(OAuthAwareUserProviderInterface $hwiOauthUserProvider = null) {
        $this->hwiOauthUserProvider = $hwiOauthUserProvider;
    }
}

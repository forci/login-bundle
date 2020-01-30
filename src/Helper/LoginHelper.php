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

use Forci\Bundle\LoginBundle\HWIOAuth\OAuthToken;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
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

    /**
     * @param string|array $accessToken
     *
     * @throws \InvalidArgumentException When login is not enabled for hwi oauth for this provider
     * @throws UsernameNotFoundException When user could not be found
     * @throws AuthenticationException   When state is wrong
     */
    final public function logInHWIOAuthAccessToken($accessToken, string $state, string $resourceOwner): void {
        $this->logInToken($this->getHWIOAuthUser($accessToken, $state, $resourceOwner));
    }

    /**
     * @param string|array $accessToken
     *
     * @throws \InvalidArgumentException When login is not enabled for hwi oauth for this provider
     * @throws UsernameNotFoundException When user could not be found
     * @throws AuthenticationException   When state is wrong
     */
    final public function rememberHWIOAuthAccessToken($accessToken, string $state, string $resourceOwner, Response $response): void {
        $this->logInToken($this->getHWIOAuthUser($accessToken, $state, $resourceOwner), [
            'remember_me' => $response
        ]);
    }

    private function getHWIOAuthUser($accessToken, string $state, string $resourceOwner): TokenInterface {
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

        return $token;
    }

    protected function logInToken(TokenInterface $token, array $options = []): void {
        $user = $token->getUser();

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

        if (isset($options['remember_me'])) {
            $response = $options['remember_me'];

            if ($response instanceof Response) {
                $this->callRememberMeServices($token, $request, $response);
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

        if (Kernel::VERSION_ID >= 50000) {
            $this->eventDispatcher->dispatch(
                new InteractiveLoginEvent($request, $token),
                SecurityEvents::INTERACTIVE_LOGIN
            );
        } else {
            $this->eventDispatcher->dispatch(
                SecurityEvents::INTERACTIVE_LOGIN,
                new InteractiveLoginEvent($request, $token)
            );
        }
    }

    protected function createUsernamePasswordToken(UserInterface $user): UsernamePasswordToken {
        return new UsernamePasswordToken($user, null, $this->getFirewallName(), $user->getRoles());
    }

    protected function createPreAuthenticatedToken(UserInterface $user): PreAuthenticatedToken {
        $token = new PreAuthenticatedToken($user, $user->getPassword(), $this->getFirewallName(), $user->getRoles());
        $token->setAuthenticated(true);

        return $token;
    }

    protected function createToken(UserInterface $user, bool $preAuthenticated): AbstractToken {
        if ($preAuthenticated) {
            return $this->createPreAuthenticatedToken($user);
        }

        return $this->createUsernamePasswordToken($user);
    }

    /**
     * @param string|array $accessToken
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

<?php

/*
 * This file is part of the ForciLoginBundle package.
 *
 * (c) Martin Kirilov <wucdbm@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Forci\Bundle\LoginBundle\HWIOAuth;

class OAuthToken extends \HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken {

    /** @var string */
    private $providerKey;

    /** @var bool */
    private $alwaysAuthenticated = false;

    /**
     * @param mixed $alwaysAuthenticated
     */
    public function setAlwaysAuthenticated($alwaysAuthenticated) {
        $this->alwaysAuthenticated = $alwaysAuthenticated;
    }

    /**
     * @param mixed $providerKey
     */
    public function setProviderKey($providerKey) {
        $this->providerKey = $providerKey;
    }

    public function getProviderKey() {
        return $this->providerKey;
    }

    public function isAuthenticated() {
        if ($this->alwaysAuthenticated) {
            return true;
        }

        return parent::isAuthenticated();
    }
}

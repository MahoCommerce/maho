<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Cookie
{
    public const XML_PATH_COOKIE_DOMAIN    = 'web/cookie/cookie_domain';
    public const XML_PATH_COOKIE_PATH      = 'web/cookie/cookie_path';
    public const XML_PATH_COOKIE_LIFETIME  = 'web/cookie/cookie_lifetime';
    public const XML_PATH_COOKIE_HTTPONLY  = 'web/cookie/cookie_httponly';
    public const XML_PATH_COOKIE_SAMESITE  = 'web/cookie/cookie_samesite';

    public const DEFAULT_COOKIE_LIFETIME   = 60 * 60 * 24 * 365;

    protected $_lifetime;

    /**
     * Store object
     *
     * @var Mage_Core_Model_Store|null
     */
    protected $_store;

    /**
     * Set Store object
     *
     * @param bool|int|Mage_Core_Model_Store|null|string $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->_store = Mage::app()->getStore($store);
        return $this;
    }

    /**
     * Retrieve Store object
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        if (is_null($this->_store)) {
            $this->_store = Mage::app()->getStore();
        }
        return $this->_store;
    }

    /**
     * Retrieve Request object
     *
     * @return Mage_Core_Controller_Request_Http
     */
    protected function _getRequest()
    {
        return Mage::app()->getRequest();
    }

    /**
     * Retrieve Response object
     *
     * @return Mage_Core_Controller_Response_Http
     */
    protected function _getResponse()
    {
        return Mage::app()->getResponse();
    }

    /**
     * Retrieve Domain for cookie
     *
     * @return string
     */
    public function getDomain()
    {
        $domain = $this->getConfigDomain();
        if (empty($domain)) {
            $domain = $this->_getRequest()->getHttpHost();
        }
        return $domain;
    }

    /**
     * Retrieve Config Domain for cookie
     *
     * @return string
     */
    public function getConfigDomain()
    {
        return (string) Mage::getStoreConfig(self::XML_PATH_COOKIE_DOMAIN, $this->getStore());
    }

    /**
     * Retrieve Path for cookie
     *
     * @return string
     */
    public function getPath()
    {
        $path = Mage::getStoreConfig(self::XML_PATH_COOKIE_PATH, $this->getStore());
        if (empty($path)) {
            $path = $this->_getRequest()->getBasePath();
        }
        return $path;
    }

    /**
     * Retrieve cookie lifetime
     *
     * @return int
     */
    public function getLifetime()
    {
        return $this->_lifetime ?? self::DEFAULT_COOKIE_LIFETIME;
    }

    /**
     * Set cookie lifetime
     *
     * @param int $lifetime
     * @return $this
     */
    public function setLifetime($lifetime)
    {
        $this->_lifetime = (int) $lifetime;
        return $this;
    }

    /**
     * Retrieve use HTTP only flag
     *
     * @return bool|null
     */
    public function getHttponly()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_COOKIE_HTTPONLY, $this->getStore());
    }

    /**
     * Retrieve use SameSite
     */
    public function getSameSite(): string
    {
        $value = Mage::getStoreConfig(self::XML_PATH_COOKIE_SAMESITE, $this->getStore());

        // Do not permit SameSite=None on unsecure pages, upgrade to Lax
        // https://developers.google.com/search/blog/2020/01/get-ready-for-new-samesitenone-secure
        if ($value === 'None' && $this->isSecure() === false) {
            Mage::log('SameSite cookie attribute downgraded from None to Lax: HTTPS is required for SameSite=None', Mage::LOG_WARNING);
            return 'Lax';
        }

        return $value;
    }

    /**
     * Retrieve use secure cookie
     *
     * @return bool
     */
    public function isSecure()
    {
        if ($this->getStore()->isAdmin()) {
            return $this->_getRequest()->isSecure();
        }
        // Use secure cookie if unsecure base url is actually secure
        if (preg_match('/^https:/', $this->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, false))) {
            return true;
        }
        return false;
    }

    /**
     * Set cookie
     *
     * @param string $name The cookie name
     * @param string $value The cookie value
     * @param int|bool $period Lifetime period
     * @param string $path
     * @param string $domain
     * @param int|bool $secure
     * @param bool $httponly
     * @param string $sameSite
     * @return $this
     */
    public function set($name, $value, $period = null, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        /**
         * Check headers sent
         */
        if (!$this->_getResponse()->canSendHeaders(false)) {
            return $this;
        }

        $period ??= $this->getLifetime();
        if ($period === true) {
            $period = self::DEFAULT_COOKIE_LIFETIME;
        }
        if ($period == 0) {
            $expires = 0;
        } else {
            $expires = time() + $period;
        }

        setcookie(
            $name,
            (string) $value,
            [
                'expires'  => $expires,
                'path'     => $path ?? $this->getPath(),
                'domain'   => $domain ?? $this->getDomain(),
                'secure'   => $secure ?? $this->isSecure(),
                'httponly' => $httponly ?? $this->getHttponly(),
                'samesite' => $sameSite ?? $this->getSameSite(),
            ],
        );

        return $this;
    }

    /**
     * Postpone cookie expiration time if cookie value defined
     *
     * @param string $name The cookie name
     * @param int $period Lifetime period
     * @param string $path
     * @param string $domain
     * @param int|bool $secure
     * @param bool $httponly
     * @param string $sameSite
     * @return $this
     */
    public function renew($name, $period = null, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        $value = $this->_getRequest()->getCookie($name, false);
        if ($value !== false) {
            $this->set($name, $value, $period, $path, $domain, $secure, $httponly, $sameSite);
        }
        return $this;
    }

    /**
     * Retrieve cookie or false if not exists
     *
     * @param string $name The cookie name
     * @return mixed
     */
    public function get($name = null)
    {
        return $this->_getRequest()->getCookie($name, false);
    }

    /**
     * Delete cookie
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     * @param int|bool $secure
     * @param int|bool $httponly
     * @param string $sameSite
     * @return $this
     */
    public function delete($name, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        return $this->set($name, '', null, $path, $domain, $secure, $httponly, $sameSite);
    }
}

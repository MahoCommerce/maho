<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

/**
 * @package    Mage_Adminhtml
 *
 * @method bool getNoSecret()
 * @method $this setNoSecret(bool $value)
 */
class Mage_Adminhtml_Model_Url extends Mage_Core_Model_Url
{
    /**
     * Secret key query param name
     */
    public const SECRET_KEY_PARAM_NAME = 'key';

    /**
     * Retrieve is secure mode for ULR logic
     *
     * @return bool
     */
    #[\Override]
    public function getSecure()
    {
        if ($this->hasData('secure_is_forced')) {
            return $this->getData('secure');
        }
        return Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_SECURE_IN_ADMINHTML);
    }

    /**
     * Force strip secret key param if _nosecret param specified
     *
     * @return Mage_Core_Model_Url
     */
    #[\Override]
    public function setRouteParams(array $data, $unsetOldParams = true)
    {
        if (isset($data['_nosecret'])) {
            $this->setNoSecret(true);
            unset($data['_nosecret']);
        } else {
            $this->setNoSecret(false);
        }

        return parent::setRouteParams($data, $unsetOldParams);
    }

    /**
     * Custom logic to retrieve Urls
     *
     * @param string $routePath
     * @param array $routeParams
     * @return string
     */
    #[\Override]
    public function getUrl($routePath = null, $routeParams = null)
    {
        $cacheSecretKey = false;
        if (is_array($routeParams) && isset($routeParams['_cache_secret_key'])) {
            unset($routeParams['_cache_secret_key']);
            $cacheSecretKey = true;
        }

        $result = parent::getUrl($routePath, $routeParams);
        if (!$this->useSecretKey()) {
            return $result;
        }

        $route = $this->getRouteName() ?: '*';
        $controller = $this->getControllerName() ?: $this->getDefaultControllerName();
        $action = $this->getActionName() ?: $this->getDefaultActionName();

        if ($cacheSecretKey) {
            $secret = [self::SECRET_KEY_PARAM_NAME => "\${$controller}/{$action}\$"];
        } else {
            $secret = [self::SECRET_KEY_PARAM_NAME => $this->getSecretKey($controller, $action)];
        }
        if (is_array($routeParams)) {
            $routeParams = array_merge($secret, $routeParams);
        } else {
            $routeParams = $secret;
        }
        if (is_array($this->getRouteParams())) {
            $routeParams = array_merge($this->getRouteParams(), $routeParams);
        }

        return parent::getUrl("{$route}/{$controller}/{$action}", $routeParams);
    }

    /**
     * Generate secret key for controller and action based on form key.
     *
     * $formKey overrides the current session's form key as the salt; pass it to mint a key
     * for a different session (e.g. tests deep-linking with a browser session's form key).
     *
     * @param string $controller Controller name
     * @param string $action Action name
     * @return string
     */
    public function getSecretKey($controller = null, $action = null, ?string $formKey = null)
    {
        $salt = $formKey ?? Mage::getSingleton('core/session')->getFormKey();

        // Validate against what the user actually requested: after _forward() the dispatched
        // names change (e.g. catalog_category/index forwards to edit) but the URL's key was
        // minted for the original action, so the before-forward snapshot takes precedence.
        // Dispatched names come next; positional path parsing assumes the classic
        // admin/<controller>/<action> shape and mis-slices legacy:migrate-routes routes that
        // carry an extra frontName segment, so it stays only as a pre-dispatch fallback.
        if (!$controller || !$action) {
            $p = explode('/', trim($this->getRequest()->getOriginalPathInfo(), '/'));
            $controller = $controller
                ?: $this->getRequest()->getBeforeForwardInfo('controller_name')
                ?: $this->getRequest()->getControllerName()
                ?: (empty($p[1]) ? null : $p[1]);
            $action = $action
                ?: $this->getRequest()->getBeforeForwardInfo('action_name')
                ?: $this->getRequest()->getActionName()
                ?: (empty($p[2]) ? null : $p[2]);
        }

        // Normalize case so the hash matches regardless of how the caller cased the
        // identifiers. The URL emitted by the Symfony generator preserves the case
        // declared in #[Route], but the menu placeholder, getUrl(*/*/foo) shorthand,
        // and getOriginalPathInfo() can all surface different cases; lowercasing here
        // means generation and validation always agree.
        $secret = strtolower($controller) . strtolower($action) . $salt;
        return Mage::helper('core')->getHash($secret);
    }

    /**
     * Whether the secret key is added to the url being built. Always on except for urls that
     * opt out via _nosecret (login-flow links, RSS feeds).
     *
     * @return bool
     */
    public function useSecretKey()
    {
        return !$this->getNoSecret();
    }

    /**
     * Refresh admin menu cache etc.
     */
    public function renewSecretUrls()
    {
        Mage::app()->cleanCache([Mage_Adminhtml_Block_Page_Menu::CACHE_TAGS]);
    }
}

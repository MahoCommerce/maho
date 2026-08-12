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

        if (!$controller || !$action) {
            [$requestController, $requestAction] = $this->getSecretKeyPairs()[0] ?? [null, null];
            $controller = $controller ?: $requestController;
            $action = $action ?: $requestAction;
        }

        // Normalize case so the hash matches regardless of how the caller cased the
        // identifiers. The URL emitted by the Symfony generator preserves the case
        // declared in #[Route], but the menu placeholder, getUrl(*/*/foo) shorthand,
        // and getOriginalPathInfo() can all surface different cases; lowercasing here
        // means generation and validation always agree.
        $secret = strtolower((string) $controller) . strtolower((string) $action) . $salt;
        return Mage::helper('core')->getHash($secret);
    }

    /**
     * Controller/action pairs the current request's secret key may have been minted for,
     * most authoritative first: the before-forward snapshot (the names the user requested),
     * then the dispatched names, then the positional path segments. Path slicing assumes the
     * classic admin/<controller>/<action> shape and mis-slices legacy:migrate-routes routes
     * that carry an extra frontName segment, so it stays last.
     *
     * @return list<array{string, string}>
     */
    public function getSecretKeyPairs(): array
    {
        $request = $this->getRequest();
        $path = explode('/', trim((string) $request->getOriginalPathInfo(), '/'));

        $candidates = [
            [$request->getBeforeForwardInfo('controller_name'), $request->getBeforeForwardInfo('action_name')],
            [$request->getControllerName(), $request->getActionName()],
            [$path[1] ?? null, $path[2] ?? null],
        ];

        $pairs = [];
        foreach ($candidates as [$controller, $action]) {
            if (!$controller || !$action) {
                continue;
            }
            $pairs[strtolower($controller) . '/' . strtolower($action)] = [$controller, $action];
        }

        return array_values($pairs);
    }

    /**
     * Whether $secretKey is valid for the current request, i.e. minted for any pair
     * getSecretKeyPairs() considers plausible. The salt is the session form key, so a key
     * for any of those pairs is unforgeable without it.
     */
    public function validateSecretKey(string $secretKey): bool
    {
        foreach ($this->getSecretKeyPairs() as [$controller, $action]) {
            if (hash_equals($this->getSecretKey($controller, $action), $secretKey)) {
                return true;
            }
        }

        return false;
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

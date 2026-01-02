<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Controller_Varien_Router_Admin extends Mage_Core_Controller_Varien_Router_Standard
{
    /**
     * Fetch default path
     */
    #[\Override]
    public function fetchDefault()
    {
        // set defaults
        $d = explode('/', $this->_getDefaultPath());
        $this->getFront()->setDefault([
            'module'     => empty($d[0]) ? '' : $d[0],
            'controller' => empty($d[1]) ? 'index' : $d[1],
            'action'     => empty($d[2]) ? 'index' : $d[2],
        ]);
    }

    /**
     * Get router default request path
     * @return string
     */
    #[\Override]
    protected function _getDefaultPath()
    {
        return (string) Mage::getConfig()->getNode('default/web/default/admin');
    }

    /**
     * dummy call to pass through checking
     *
     * @return bool
     */
    #[\Override]
    protected function _beforeModuleMatch()
    {
        return true;
    }

    /**
     * checking if we installed or not and doing redirect
     *
     * @return bool
     */
    #[\Override]
    protected function _afterModuleMatch()
    {
        if (!Mage::isInstalled()) {
            Mage::app()->getFrontController()->getResponse()
                ->setRedirect(Mage::getUrl('install'))
                ->sendResponse();
            exit;
        }
        return true;
    }

    /**
     * We need to have noroute action in this router
     * not to pass dispatching to next routers
     *
     * @return bool
     */
    #[\Override]
    protected function _noRouteShouldBeApplied()
    {
        return true;
    }

    /**
     * Check whether URL for corresponding path should use https protocol
     *
     * @param string $path
     * @return bool
     */
    #[\Override]
    protected function _shouldBeSecure($path)
    {
        return str_starts_with((string) Mage::getConfig()->getNode('default/web/unsecure/base_url'), 'https')
            || Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_SECURE_IN_ADMINHTML, Mage_Core_Model_App::ADMIN_STORE_ID)
                && str_starts_with((string) Mage::getConfig()->getNode('default/web/secure/base_url'), 'https');
    }

    /**
     * Retrieve current secure url
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @return string
     */
    #[\Override]
    protected function _getCurrentSecureUrl($request)
    {
        return Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->getBaseUrl('link', true) . ltrim($request->getPathInfo(), '/');
    }

    /**
     * Emulate custom admin url
     *
     * @param string $configArea
     * @param bool $useRouterName
     */
    #[\Override]
    public function collectRoutes($configArea, $useRouterName)
    {
        if ((string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_USE_CUSTOM_ADMIN_PATH)) {
            $customUrl = (string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_CUSTOM_ADMIN_PATH);
            $xmlPath = Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME;
            if ((string) Mage::getConfig()->getNode($xmlPath) != $customUrl) {
                Mage::getConfig()->setNode($xmlPath, $customUrl, true);
            }
        }
        parent::collectRoutes($configArea, $useRouterName);
    }

    /**
     * Add module definition to routes.
     */
    #[\Override]
    public function addModule($frontName, $moduleName, $routeName)
    {
        $configRouterFrontName = (string) Mage::getConfig()->getNode(
            Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME,
        );
        if ($frontName == $configRouterFrontName) {
            return parent::addModule($frontName, $moduleName, $routeName);
        }
        return $this;
    }

    /**
     * Check if current controller instance is allowed in current router.
     *
     * @param Mage_Core_Controller_Varien_Action $controllerInstance
     * @return true
     */
    #[\Override]
    protected function _validateControllerInstance($controllerInstance)
    {
        return true;
    }
}

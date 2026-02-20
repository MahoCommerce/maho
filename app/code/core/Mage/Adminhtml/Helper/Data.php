<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Helper_Data extends Mage_Adminhtml_Helper_Help_Mapping
{
    public const XML_PATH_ADMINHTML_ROUTER_FRONTNAME   = 'admin/routers/adminhtml/args/frontName';
    public const XML_PATH_USE_CUSTOM_ADMIN_URL         = 'default/admin/url/use_custom';
    public const XML_PATH_USE_CUSTOM_ADMIN_PATH        = 'default/admin/url/use_custom_path';
    public const XML_PATH_CUSTOM_ADMIN_PATH            = 'default/admin/url/custom_path';
    public const XML_PATH_ADMINHTML_SECURITY_USE_FORM_KEY = 'admin/security/use_form_key';

    protected $_moduleName = 'Mage_Adminhtml';

    /**
     * List of adminhtml front names, by default only "admin"
     *
     * @var list<string>
     */
    protected $adminFrontNames;

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    public static function getUrl($route = '', $params = [])
    {
        return Mage::getModel('adminhtml/url')->getUrl($route, $params);
    }

    /**
     * @return false|int
     */
    public function getCurrentUserId()
    {
        if (Mage::getSingleton('admin/session')->getUser()) {
            return Mage::getSingleton('admin/session')->getUser()->getId();
        }
        return false;
    }

    /**
     * Decode filter string
     *
     * @param string $filterString
     * @return array
     */
    public function prepareFilterString($filterString)
    {
        $data = [];
        $filterString = base64_decode($filterString);
        parse_str($filterString, $data);
        array_walk_recursive($data, [$this, 'decodeFilter']);
        return $data;
    }

    /**
     * Decode URL encoded filter value recursive callback method
     *
     * @param string $value
     */
    public function decodeFilter(&$value)
    {
        $value = trim(rawurldecode($value));
    }

    /**
     * Check if enabled "Add Secret Key to URLs" functionality
     *
     * @return bool
     */
    public function isEnabledSecurityKeyUrl()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ADMINHTML_SECURITY_USE_FORM_KEY);
    }

    /**
     * Check if url starts with one of the admin front names
     */
    public function isAdminFrontNameMatched(string $url): bool
    {
        if ($this->adminFrontNames === null) {
            $useCustomAdminPath = (bool) (string) Mage::getConfig()->getNode(self::XML_PATH_USE_CUSTOM_ADMIN_PATH);
            $customAdminPath = (string) Mage::getConfig()->getNode(self::XML_PATH_CUSTOM_ADMIN_PATH);
            $adminPath = ($useCustomAdminPath) ? $customAdminPath : null;

            if (!$adminPath) {
                $adminPath = (string) Mage::getConfig()
                    ->getNode(self::XML_PATH_ADMINHTML_ROUTER_FRONTNAME);
            }
            $this->adminFrontNames = [$adminPath];

            // Check for other modules that can use admin router (a lot of Magento extensions do that)
            $adminFrontNameNodes = Mage::getConfig()->getNode('admin/routers')
                ->xpath('*[not(self::adminhtml) and use = "admin"]/args/frontName');

            if (is_array($adminFrontNameNodes)) {
                foreach ($adminFrontNameNodes as $frontNameNode) {
                    $this->adminFrontNames[] = (string) $frontNameNode;
                }
            }
        }

        $baseUrl = Mage::app()->getRequest()->getBaseUrl();

        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        if (str_starts_with($path, $baseUrl)) {
            $path = substr($path, strlen($baseUrl));
        }

        $frontName = strtok(ltrim($path, '/'), '/');

        return in_array($frontName, $this->adminFrontNames);
    }
}

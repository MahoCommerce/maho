<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Admin_Model_Config extends \Maho\Simplexml\Config
{
    /**
     * adminhtml.xml merged config
     *
     * @var \Maho\Simplexml\Config
     */
    protected $_adminhtmlConfig;

    /**
     * Load config from merged adminhtml.xml files
     */
    public function __construct()
    {
        parent::__construct();
        $this->setCacheId('adminhtml_acl_menu_config');

        $adminhtmlConfig = Mage::app()->loadCache($this->getCacheId());
        if ($adminhtmlConfig) {
            $this->_adminhtmlConfig = new \Maho\Simplexml\Config($adminhtmlConfig);
        } else {
            $adminhtmlConfig = new \Maho\Simplexml\Config();
            $adminhtmlConfig->loadString('<?xml version="1.0"?><config></config>');
            Mage::getConfig()->loadModulesConfiguration('adminhtml.xml', $adminhtmlConfig);
            $this->_adminhtmlConfig = $adminhtmlConfig;

            if (Mage::app()->useCache('config')) {
                Mage::app()->saveCache(
                    $adminhtmlConfig->getXmlString(),
                    $this->getCacheId(),
                    [Mage_Core_Model_Config::CACHE_TAG],
                );
            }
        }
    }

    /**
     * Load Acl resources from config
     *
     * @param Mage_Core_Model_Config_Element|\Maho\Simplexml\Element $resource
     * @param string $parentName
     * @return $this
     */
    public function loadAclResources(Mage_Admin_Model_Acl $acl, $resource = null, $parentName = null)
    {
        if (is_null($resource)) {
            $resource = $this->getAdminhtmlConfig()->getNode('acl/resources');
            $resourceName = null;
        } else {
            $resourceName = (is_null($parentName) ? '' : $parentName . '/') . $resource->getName();
            $acl->addResource(Mage::getModel('admin/acl_resource', $resourceName), $parentName);
        }

        if (isset($resource->all)) {
            $acl->addResource(Mage::getModel('admin/acl_resource', 'all'));
        }

        if (isset($resource->admin)) {
            $children = $resource->admin;
        } elseif (isset($resource->children)) {
            $children = $resource->children->children();
        }

        if (empty($children)) {
            return $this;
        }

        foreach ($children as $res) {
            if ($res->disabled == 1) {
                continue;
            }
            $this->loadAclResources($acl, $res, $resourceName);
        }
        return $this;
    }

    /**
     * Get acl assert config
     *
     * @param string $name
     * @return false|SimpleXMLElement|\Maho\Simplexml\Element|Mage_Core_Model_Config_Element
     */
    public function getAclAssert($name = '')
    {
        $asserts = $this->getNode('admin/acl/asserts');
        if ($name === '') {
            return $asserts;
        }

        return $asserts->$name ?? false;
    }

    /**
     * Retrieve privilege set by name
     *
     * @param string $name
     * @return false|SimpleXMLElement|\Maho\Simplexml\Element
     */
    public function getAclPrivilegeSet($name = '')
    {
        $sets = $this->getNode('admin/acl/privilegeSets');
        if ($name === '') {
            return $sets;
        }

        return $sets->$name ?? false;
    }

    /**
     * Retrieve xml config
     *
     * @return \Maho\Simplexml\Config
     */
    public function getAdminhtmlConfig()
    {
        return $this->_adminhtmlConfig;
    }

    /**
     * Get menu item label by item path
     *
     * @param string $path
     * @return string
     */
    public function getMenuItemLabel($path)
    {
        $moduleName = 'adminhtml';
        $menuNode = $this->getAdminhtmlConfig()->getNode('menu/' . str_replace('/', '/children/', trim($path, '/')));
        if ($menuNode->getAttribute('module')) {
            $moduleName = (string) $menuNode->getAttribute('module');
        }
        return Mage::helper($moduleName)->__((string) $menuNode->title);
    }
}

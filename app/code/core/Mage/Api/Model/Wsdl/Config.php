<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api_Model_Wsdl_Config extends Mage_Api_Model_Wsdl_Config_Base
{
    protected static $_namespacesPrefix = null;

    public function __construct($sourceData = null)
    {
        $this->setCacheId(Mage::helper('api')->getCacheId());
        parent::__construct($sourceData);
    }

    /**
     * Return wsdl content
     *
     * @return string|bool
     */
    public function getWsdlContent()
    {
        return $this->_xml->asXML();
    }

    /**
     * Return namespaces with their prefix
     *
     * @return array
     */
    public static function getNamespacesPrefix()
    {
        if (is_null(self::$_namespacesPrefix)) {
            self::$_namespacesPrefix = [];
            $config = Mage::getSingleton('api/config')->getNode('v2/wsdl/prefix')->children();
            foreach ($config as $prefix => $namespace) {
                self::$_namespacesPrefix[$namespace->asArray()] = $prefix;
            }
        }
        return self::$_namespacesPrefix;
    }

    /**
     * @return Mage_Core_Model_Cache
     */
    #[\Override]
    public function getCache()
    {
        return Mage::app()->getCache();
    }

    /**
     * @param string $id
     * @return bool|mixed
     */
    #[\Override]
    protected function _loadCache($id)
    {
        return Mage::app()->loadCache($id);
    }

    /**
     * @param string $data
     * @param string $id
     * @param array $tags
     * @param int|false|null $lifetime
     * @return bool|Mage_Core_Model_App
     */
    #[\Override]
    protected function _saveCache($data, $id, $tags = [], $lifetime = false)
    {
        return Mage::app()->saveCache($data, $id, $tags, $lifetime);
    }

    /**
     * @param string $id
     * @return Mage_Core_Model_App
     */
    #[\Override]
    protected function _removeCache($id)
    {
        return Mage::app()->removeCache($id);
    }

    /**
     * @return $this
     */
    public function init()
    {
        $this->setCacheChecksum(null);
        $saveCache = true;

        if (Mage::app()->useCache('config')) {
            $loaded = $this->loadCache();
            if ($loaded) {
                return $this;
            }
        }

        $moduleDir = Mage::getConfig()->getModuleDir('etc', 'Mage_Api');

        $mergeWsdl = new Mage_Api_Model_Wsdl_Config_Base();
        $mergeWsdl->setHandler($this->getHandler());

        if (Mage::helper('api/data')->isComplianceWSI()) {
            /**
             * Exclude Mage_Api wsdl xml file because it used for previous version
             * of API wsdl declaration
             */
            $mergeWsdl->addLoadedFile(Maho::findFile("$moduleDir/wsi.xml"));

            // Base wsi file
            $this->loadFile(Maho::findFile("$moduleDir/wsi.xml"));

            Mage::getConfig()->loadModulesConfiguration('wsi.xml', $this, $mergeWsdl);
        } else {
            /**
             * Exclude Mage_Api wsdl xml file because it used for previous version
             * of API wsdl declaration
             */
            $mergeWsdl->addLoadedFile(Maho::findFile("$moduleDir/wsdl.xml"));

            // Base wsdl file
            $this->loadFile(Maho::findFile("$moduleDir/wsdl2.xml"));

            Mage::getConfig()->loadModulesConfiguration('wsdl.xml', $this, $mergeWsdl);
        }

        if (Mage::app()->useCache('config')) {
            $this->saveCache(['config']);
        }

        return $this;
    }

    /**
     * Return Xml of node as string
     *
     * @return string|bool
     */
    #[\Override]
    public function getXmlString()
    {
        return $this->getNode()->asXML();
    }
}

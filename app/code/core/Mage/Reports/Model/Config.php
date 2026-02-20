<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Config extends \Maho\DataObject
{
    /**
     * @return string
     */
    public function getGlobalConfig()
    {
        $dom = new DOMDocument();
        $dom -> load(Mage::getModuleDir('etc', 'Mage_Reports') . DS . 'flexConfig.xml');

        $baseUrl = $dom -> createElement('baseUrl');
        $baseUrl -> nodeValue = Mage::getBaseUrl();

        $dom -> documentElement -> appendChild($baseUrl);

        return $dom -> saveXML();
    }

    /**
     * @return false|string
     */
    public function getLanguage()
    {
        return file_get_contents(Mage::getModuleDir('etc', 'Mage_Reports') . DS . 'flexLanguage.xml');
    }

    /**
     * @return false|string
     */
    public function getDashboard()
    {
        return file_get_contents(Mage::getModuleDir('etc', 'Mage_Reports') . DS . 'flexDashboard.xml');
    }
}

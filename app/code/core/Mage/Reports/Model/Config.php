<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
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

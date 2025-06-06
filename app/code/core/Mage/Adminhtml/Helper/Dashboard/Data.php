<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Helper_Dashboard_Data extends Mage_Core_Helper_Data
{
    protected $_moduleName = 'Mage_Adminhtml';

    protected $_locale = null;
    protected $_stores = null;

    /**
     * Retrieve stores configured in system.
     *
     * @return Mage_Core_Model_Resource_Store_Collection
     */
    public function getStores()
    {
        if (!$this->_stores) {
            $this->_stores = Mage::app()->getStore()->getResourceCollection()->load();
        }

        return $this->_stores;
    }

    /**
     * Retrieve number of loaded stores
     *
     * @return int
     */
    public function countStores()
    {
        return count($this->getStores()->getItems());
    }

    /**
     * Prepare array with periods for dashboard graphs
     *
     * @return array
     */
    public function getDatePeriods()
    {
        return [
            '24h' => $this->__('Last 24 Hours'),
            '7d'  => $this->__('Last 7 Days'),
            '1m'  => $this->__('Current Month'),
            '3m'  => $this->__('Last 3 Month'),
            '6m'  => $this->__('Last 6 Month'),
            '1y'  => $this->__('YTD'),
            '2y'  => $this->__('2YTD'),
        ];
    }

    /**
     * Create data hash to ensure that we got valid
     * data, and it is not changed by someone else.
     *
     * @param string $data
     * @return string
     */
    public function getChartDataHash($data)
    {
        $secret = (string) Mage::getConfig()->getNode(Mage_Core_Model_App::XML_PATH_INSTALL_DATE);
        return md5($data . $secret);
    }
}

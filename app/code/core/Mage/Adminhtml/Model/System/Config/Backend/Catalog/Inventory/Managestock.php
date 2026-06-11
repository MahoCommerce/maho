<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Catalog_Inventory_Managestock extends Mage_Core_Model_Config_Data
{
    /**
     * @var Mage_CatalogInventory_Model_Stock_Status
     */
    protected $_stockStatusModel;

    public function __construct($parameters = [])
    {
        if (!empty($parameters['stock_status_model'])) {
            $this->_stockStatusModel = $parameters['stock_status_model'];
        } else {
            $this->_stockStatusModel = Mage::getSingleton('cataloginventory/stock_status');
        }

        parent::__construct($parameters);
    }

    /**
     * After change Catalog Inventory Manage value process
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        if ($this->getValue() != $this->getOldValue()) {
            $this->_stockStatusModel->rebuild();
        }

        return $this;
    }
}

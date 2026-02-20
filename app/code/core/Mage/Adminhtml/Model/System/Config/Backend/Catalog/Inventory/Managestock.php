<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

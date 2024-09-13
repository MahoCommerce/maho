<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Reports Compared Product Index Resource Collection
 *
 * @category   Mage
 * @package    Mage_Reports
 */
class Mage_Reports_Model_Resource_Product_Index_Compared_Collection extends Mage_Reports_Model_Resource_Product_Index_Collection_Abstract
{
    /**
     * Retrieve Product Index table name
     *
     * @return string
     */
    #[\Override]
    protected function _getTableName()
    {
        return $this->getTable('reports/compared_product_index');
    }
}

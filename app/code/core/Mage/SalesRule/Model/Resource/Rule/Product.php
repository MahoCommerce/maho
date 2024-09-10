<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * SalesRule Model Resource Rule_Product
 *
 * @category   Mage
 * @package    Mage_SalesRule
 */
class Mage_SalesRule_Model_Resource_Rule_Product extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('salesrule/rule_product', 'product_rule_id');
    }
}

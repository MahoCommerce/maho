<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Sales report coupons collection
 *
 * @category   Mage
 * @package    Mage_SalesRule
 */
class Mage_SalesRule_Model_Resource_Report_Updatedat_Collection extends Mage_SalesRule_Model_Resource_Report_Collection
{
    /**
     * Aggregated Data Table
     *
     * @var string
     */
    protected $_aggregationTable = 'salesrule/coupon_aggregated_updated';
}

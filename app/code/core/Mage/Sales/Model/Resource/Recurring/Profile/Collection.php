<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Recurring profile collection
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Resource_Recurring_Profile_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * @var string
     */
    protected $_eventPrefix    = 'sales_recurring_profile_collection';

    /**
     * @var string
     */
    protected $_eventObject    = 'recurring_profile_collection';

    /**
     * Entity initialization
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/recurring_profile');
    }
}

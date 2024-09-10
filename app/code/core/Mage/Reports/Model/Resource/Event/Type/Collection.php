<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Report event types collection
 *
 * @category   Mage
 * @package    Mage_Reports
 */
class Mage_Reports_Model_Resource_Event_Type_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/event_type');
    }

    /**
     * Return option array
     *
     * @return array
     */
    #[\Override]
    public function toOptionArray()
    {
        return parent::_toOptionArray('event_type_id', 'event_name');
    }
}

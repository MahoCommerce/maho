<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Review statuses collection
 *
 * @category   Mage
 * @package    Mage_Review
 */
class Mage_Review_Model_Resource_Review_Status_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Review status table
     *
     * @var string
     */
    protected $_reviewStatusTable;

    /**
     * Collection model initialization
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('review/review_status');
    }

    /**
     * Convert items array to array for select options
     *
     * @return array
     */
    #[\Override]
    public function toOptionArray()
    {
        return parent::_toOptionArray('status_id', 'status_code');
    }
}

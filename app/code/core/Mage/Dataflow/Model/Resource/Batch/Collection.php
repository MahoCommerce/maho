<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Dataflow_Model_Resource_Batch_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init model
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/batch');
    }

    /**
     * Add expire filter (for abandoned batches)
     *
     */
    public function addExpireFilter()
    {
        $date = Mage::getSingleton('core/date');
        /** @var Mage_Core_Model_Date $date */
        $lifetime = Mage_Dataflow_Model_Batch::LIFETIME;
        $expire   = $date->gmtDate(null, $date->timestamp() - $lifetime);

        $this->getSelect()->where('created_at < ?', $expire);
    }
}

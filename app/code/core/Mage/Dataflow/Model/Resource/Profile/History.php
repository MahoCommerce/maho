<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert history resource model
 *
 * @package    Mage_Dataflow
 */
class Mage_Dataflow_Model_Resource_Profile_History extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/profile_history', 'history_id');
    }

    /**
     * Sets up performed at time if needed
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        if (!$object->getPerformedAt()) {
            $object->setPerformedAt($this->formatDate(time()));
        }
        parent::_beforeSave($object);
        return $this;
    }
}

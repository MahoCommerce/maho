<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
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
            $object->setPerformedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        }
        parent::_beforeSave($object);
        return $this;
    }
}

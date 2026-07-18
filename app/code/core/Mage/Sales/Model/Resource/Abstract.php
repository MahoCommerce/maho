<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

abstract class Mage_Sales_Model_Resource_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Prepare data for save
     *
     * @return array
     */
    #[\Override]
    protected function _prepareDataForSave(Mage_Core_Model_Abstract $object)
    {
        $currentTime = Mage::app()->getLocale()->formatDateForDb('now');
        if ((!$object->getId() || $object->isObjectNew()) && !$object->getCreatedAt()) {
            $object->setCreatedAt($currentTime);
        }
        $object->setUpdatedAt($currentTime);
        return parent::_prepareDataForSave($object);
    }
}

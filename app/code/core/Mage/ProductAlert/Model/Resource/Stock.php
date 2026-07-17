<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

class Mage_ProductAlert_Model_Resource_Stock extends Mage_ProductAlert_Model_Resource_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('productalert/stock', 'alert_stock_id');
    }

    /**
     * @param Mage_ProductAlert_Model_Stock $object
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        if (is_null($object->getId()) && $object->getCustomerId()
                && $object->getProductId() && $object->getWebsiteId()
        ) {
            if ($row = $this->_getAlertRow($object)) {
                $object->addData($row);
                $object->setStatus(0);
            }
        }
        if (is_null($object->getAddDate())) {
            $object->setAddDate(Mage::app()->getLocale()->formatDateForDb('now'));
            $object->setStatus(0);
        }
        return parent::_beforeSave($object);
    }
}

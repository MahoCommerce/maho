<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Model_Resource_History extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('giftcard/history', 'history_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        if (!$object->getId()) {
            $object->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        }

        return parent::_beforeSave($object);
    }
}

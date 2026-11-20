<?php

/**
 * Resource model of the saved reports.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_SavedReport extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/saved', 'saved_report_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): static
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if ($object->isObjectNew() && !$object->getData('created_at')) {
            $object->setData('created_at', $now);
        }
        $object->setData('updated_at', $now);
        return parent::_beforeSave($object);
    }
}

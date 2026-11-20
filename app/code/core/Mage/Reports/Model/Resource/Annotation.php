<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_Annotation extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/annotation', 'annotation_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): static
    {
        if ($object->isObjectNew() && !$object->getData('created_at')) {
            $object->setData('created_at', Mage::app()->getLocale()->formatDateForDb('now'));
        }
        return parent::_beforeSave($object);
    }
}
